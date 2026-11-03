<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\ShopOrder;
use App\Models\ShopProduct;
use App\Models\VipMember;
use App\Services\AdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function index(): View
    {
        return view('admin.shop.index', [
            'products' => ShopProduct::query()->latest()->get(),
            'orders' => ShopOrder::with(['user', 'product'])->latest()->limit(50)->get(),
            'bankInfo' => Setting::value('shop_bank_info', ''),
        ]);
    }

    public function storeProduct(Request $request, AdminAuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);
        $data['is_vip'] = $request->boolean('is_vip');
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

        return back()->with('status', 'Захиалга баталгаажлаа.');
    }

    public function cancel(Request $request, ShopOrder $order, AdminAuditLogger $audit): RedirectResponse
    {
        if ($order->status === 'pending') {
            $order->update(['status' => 'cancelled']);
            $audit->record($request, 'shop.order_cancelled', $order, [], $order->code);
        }

        return back()->with('status', 'Захиалга цуцлагдлаа.');
    }
}
