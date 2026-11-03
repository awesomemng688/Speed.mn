<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\ShopOrder;
use App\Models\ShopProduct;
use App\Models\VipMember;
use App\Notifications\SiteActivityNotification;
use App\Services\AdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShopController extends Controller
{
    public function index(): View
    {
        $paid = ShopOrder::query()->where('status', 'paid');

        return view('admin.shop.index', [
            'products' => ShopProduct::query()->latest()->get(),
            'orders' => ShopOrder::with(['user', 'product'])->latest()->limit(50)->get(),
            'bankInfo' => Setting::value('shop_bank_info', ''),
            'report' => [
                'total' => (int) (clone $paid)->sum('amount'),
                'month' => (int) (clone $paid)->where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
                'paidCount' => (clone $paid)->count(),
                'pendingCount' => ShopOrder::where('status', 'pending')->count(),
            ],
        ]);
    }

    public function storeProduct(Request $request, AdminAuditLogger $audit): RedirectResponse
    {
        $data = $request->validate($this->productRules());
        unset($data['image'], $data['gallery']);
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('shop', 'public');
        }
        if ($request->hasFile('gallery')) {
            $data['gallery'] = $this->storeGallery($request);
        }
        $data['stock'] = $request->filled('stock') ? (int) $request->input('stock') : null;
        $data['is_vip'] = $request->boolean('is_vip') || $data['category'] === 'vip';
        $data['active'] = true;
        $product = ShopProduct::create($data);
        $audit->record($request, 'shop.product_created', $product, [], $product->name);

        return back()->with('status', 'Бараа нэмэгдлээ.');
    }

    public function toggleProduct(Request $request, ShopProduct $product, AdminAuditLogger $audit): RedirectResponse
    {
        $product->update(['active' => ! $product->active]);
        $audit->record($request, 'shop.product_toggled', $product, ['active' => $product->active], $product->name);

        return back()->with('status', 'Барааны төлөв шинэчлэгдлээ.');
    }

    public function destroyProduct(Request $request, ShopProduct $product, AdminAuditLogger $audit): RedirectResponse
    {
        $audit->record($request, 'shop.product_deleted', $product, [], $product->name);
        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }
        foreach ($product->gallery ?? [] as $path) {
            Storage::disk('public')->delete($path);
        }
        $product->delete();

        return back()->with('status', 'Бараа устгагдлаа.');
    }

    public function bankInfo(Request $request, AdminAuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['shop_bank_info' => ['nullable', 'string', 'max:500']]);
        Setting::put(['shop_bank_info' => $data['shop_bank_info'] ?? '']);
        $audit->record($request, 'shop.bank_info_updated');

        return back()->with('status', 'Дансны мэдээлэл хадгалагдлаа.');
    }

    public function approve(Request $request, ShopOrder $order, AdminAuditLogger $audit): RedirectResponse
    {
        $message = DB::transaction(function () use ($order): ?string {
            $order = ShopOrder::with(['user', 'product'])->lockForUpdate()->findOrFail($order->id);
            if ($order->status !== 'pending') {
                return 'Энэ захиалга аль хэдийн шийдэгдсэн байна.';
            }

            $product = $order->product;
            if ($product->is_vip) {
                $user = $order->user;
                if (! $user->steam_id) {
                    return 'Хэрэглэгчийн Steam ID алга тул VIP олгож чадсангүй.';
                }
                $days = $product->duration_days;
                $existing = VipMember::where('steam_id', $user->steam_id)->orderByDesc('expires_at')->first();
                $base = $existing && $existing->expires_at && $existing->expires_at->isFuture() ? $existing->expires_at : now();
                $expires = $days ? $base->copy()->addDays($days)->toDateString() : null;

                if ($existing && $existing->expires_at !== null) {
                    $existing->update(['expires_at' => $expires, 'amount' => $existing->amount + $order->amount, 'plan' => $product->name]);
                } else {
                    VipMember::create([
                        'steam_id' => $user->steam_id,
                        'name' => $user->name,
                        'plan' => $product->name,
                        'amount' => $order->amount,
                        'starts_at' => now()->toDateString(),
                        'expires_at' => $expires,
                        'note' => 'Дэлгүүр '.$order->code,
                    ]);
                }
            }

            $order->update(['status' => 'paid', 'paid_at' => now()]);

            return null;
        });

        if ($message) {
            return back()->with('error', $message);
        }
        $audit->record($request, 'shop.order_approved', $order, [], $order->code);
        $order->refresh()->user?->notify(new SiteActivityNotification('Захиалга баталгаажлаа', $order->code.' кодтой захиалгын төлбөр баталгаажлаа.', route('shop.index')));

        return back()->with('status', 'Захиалга баталгаажлаа.');
    }

    public function cancel(Request $request, ShopOrder $order, AdminAuditLogger $audit): RedirectResponse
    {
        $cancelled = DB::transaction(function () use ($order): ?ShopOrder {
            $locked = ShopOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status !== 'pending') {
                return null;
            }
            $locked->update(['status' => 'cancelled']);
            ShopProduct::query()->whereKey($locked->shop_product_id)->whereNotNull('stock')->increment('stock');

            return $locked;
        });

        if ($cancelled) {
            $audit->record($request, 'shop.order_cancelled', $cancelled, [], $cancelled->code);
            $cancelled->user?->notify(new SiteActivityNotification('Захиалга цуцлагдлаа', $cancelled->code.' кодтой захиалга цуцлагдлаа.', route('shop.index')));
        }

        return back()->with('status', 'Захиалга цуцлагдлаа.');
    }

    public function editProduct(ShopProduct $product): View
    {
        return view('admin.shop.edit', ['product' => $product]);
    }

    public function updateProduct(Request $request, ShopProduct $product, AdminAuditLogger $audit): RedirectResponse
    {
        $data = $request->validate($this->productRules());
        unset($data['image'], $data['gallery']);
        if ($request->hasFile('image')) {
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            $data['image_path'] = $request->file('image')->store('shop', 'public');
        }
        if ($request->hasFile('gallery')) {
            foreach ($product->gallery ?? [] as $old) {
                Storage::disk('public')->delete($old);
            }
            $data['gallery'] = $this->storeGallery($request);
        }
        $data['is_vip'] = $request->boolean('is_vip') || $data['category'] === 'vip';
        $data['stock'] = $request->filled('stock') ? (int) $request->input('stock') : null;
        $product->update($data);
        $audit->record($request, 'shop.product_updated', $product, [], $product->name);

        return redirect()->route('admin.shop.index')->with('status', 'Бараа шинэчлэгдлээ.');
    }

    public function export(): StreamedResponse
    {
        $orders = ShopOrder::with(['user', 'product'])->latest()->get();

        return response()->streamDownload(function () use ($orders): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Код', 'Бараа', 'Дүн', 'Төлөв', 'Хэрэглэгч', 'Steam ID', 'Нэмэлт', 'Үүссэн', 'Төлсөн']);
            foreach ($orders as $o) {
                $cells = [$o->code, $o->product?->name, $o->amount, ShopOrder::STATUSES[$o->status] ?? $o->status, $o->user?->name, $o->user?->steam_id, $o->details, $o->created_at?->format('Y-m-d H:i'), $o->paid_at?->format('Y-m-d H:i')];
                // Neutralise spreadsheet formula injection.
                $cells = array_map(fn ($c) => is_string($c) && preg_match('/^[=+\-@\t\r]/', $c) ? "'".$c : $c, $cells);
                fputcsv($out, $cells);
            }
            fclose($out);
        }, 'shop-orders-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function productRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', Rule::in(array_keys(ShopProduct::CATEGORIES))],
            'price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'gallery' => ['nullable', 'array', 'max:6'],
            'gallery.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    private function storeGallery(Request $request): array
    {
        return collect($request->file('gallery', []))->map(fn ($file) => $file->store('shop', 'public'))->values()->all();
    }
}
