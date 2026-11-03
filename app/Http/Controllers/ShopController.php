<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\ShopOrder;
use App\Models\ShopProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShopController extends Controller
{
    public function index(Request $request): View
    {
        $category = array_key_exists((string) $request->query('category'), ShopProduct::CATEGORIES) ? (string) $request->query('category') : '';
        $search = trim(mb_substr((string) $request->query('q', ''), 0, 100));
        $sort = in_array($request->query('sort'), ['price_asc', 'price_desc', 'new'], true) ? $request->query('sort') : 'price_asc';
        $escaped = addcslashes($search, '%_\\');

        $products = ShopProduct::query()->where('active', true)
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$escaped}%"))
            ->when($sort === 'price_desc', fn ($q) => $q->orderByDesc('price'), fn ($q) => $sort === 'new' ? $q->orderByDesc('id') : $q->orderBy('price'))
            ->get();

        $orders = $request->user()
            ? ShopOrder::with('product')->where('user_id', $request->user()->id)->latest()->limit(20)->get()
            : collect();

        return view('shop.index', [
            'products' => $products,
            'category' => $category,
            'search' => $search,
            'sort' => $sort,
            'orders' => $orders,
            'bankInfo' => Setting::value('shop_bank_info', ''),
        ]);
    }

    public function show(ShopProduct $product): View
    {
        abort_unless($product->active, 404);

        return view('shop.show', ['product' => $product, 'bankInfo' => Setting::value('shop_bank_info', '')]);
    }

    public function order(Request $request, ShopProduct $product): RedirectResponse
    {
        abort_unless($product->active, 404);
        $request->validate(['details' => ['nullable', 'string', 'max:255']]);

        $pending = ShopOrder::where('user_id', $request->user()->id)->where('status', 'pending')->count();
        if ($pending >= 5) {
            return back()->with('error', 'Хүлээгдэж буй захиалга хэт олон байна. Эхлээд тэдгээрийг баталгаажуулах эсвэл цуцлана уу.');
        }

        $order = DB::transaction(function () use ($request, $product): ?ShopOrder {
            $locked = ShopProduct::query()->lockForUpdate()->findOrFail($product->id);
            if (! $locked->active || ! $locked->inStock()) {
                return null;
            }
            if ($locked->stock !== null) {
                $locked->decrement('stock');
            }

            return ShopOrder::create([
                'user_id' => $request->user()->id,
                'shop_product_id' => $locked->id,
                'code' => 'SP'.strtoupper(Str::random(8)),
                'amount' => $locked->price,
                'details' => $request->input('details'),
            ]);
        });

        if (! $order) {
            return back()->with('error', 'Уучлаарай, энэ бараа дууссан байна.');
        }

        return redirect()->route('shop.index')->with('status', 'Захиалга үүслээ. Гүйлгээний утгад '.$order->code.' кодыг бичнэ үү.');
    }

    public function cancel(Request $request, ShopOrder $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        $cancelled = DB::transaction(function () use ($order): bool {
            $locked = ShopOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status !== 'pending') {
                return false;
            }
            $locked->update(['status' => 'cancelled']);
            ShopProduct::query()->whereKey($locked->shop_product_id)->whereNotNull('stock')->increment('stock');

            return true;
        });

        return $cancelled
            ? back()->with('status', 'Захиалга цуцлагдлаа.')
            : back()->with('error', 'Энэ захиалгыг цуцлах боломжгүй.');
    }

    public function receipt(Request $request, ShopOrder $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        $request->validate(['receipt' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072']]);

        if ($order->status !== 'pending') {
            return back()->with('error', 'Энэ захиалгад баримт хавсаргах боломжгүй.');
        }
        if ($order->receipt_path) {
            Storage::disk('local')->delete($order->receipt_path);
        }
        $order->update(['receipt_path' => $request->file('receipt')->store('shop-receipts', 'local')]);

        return back()->with('status', 'Төлбөрийн баримт хавсаргагдлаа.');
    }

    public function showReceipt(Request $request, ShopOrder $order): StreamedResponse
    {
        abort_unless($order->user_id === $request->user()->id || $request->user()->is_admin, 404);
        abort_unless($order->receipt_path && Storage::disk('local')->exists($order->receipt_path), 404);

        return Storage::disk('local')->response($order->receipt_path, null, ['X-Content-Type-Options' => 'nosniff']);
    }
}
