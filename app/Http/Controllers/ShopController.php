<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\ShopOrder;
use App\Models\ShopProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function index(Request $request): View
    {
        $orders = $request->user()
            ? ShopOrder::with('product')->where('user_id', $request->user()->id)->latest()->limit(20)->get()
            : collect();

        return view('shop.index', [
            'products' => ShopProduct::query()->where('active', true)
                ->when(array_key_exists((string) $request->query('category'), ShopProduct::CATEGORIES), fn ($q) => $q->where('category', $request->query('category')))
                ->orderBy('price')->get(),
            'category' => (string) $request->query('category', ''),
            'orders' => $orders,
            'bankInfo' => Setting::value('shop_bank_info', ''),
        ]);
    }

    public function order(Request $request, ShopProduct $product): RedirectResponse
    {
        abort_unless($product->active, 404);

        $pending = ShopOrder::where('user_id', $request->user()->id)->where('status', 'pending')->count();
        if ($pending >= 5) {
            return back()->with('error', 'Хүлээгдэж буй захиалга хэт олон байна. Эхлээд тэдгээрийг баталгаажуулна уу.');
        }

        $request->validate(['details' => ['nullable', 'string', 'max:255']]);

        $order = ShopOrder::create([
            'user_id' => $request->user()->id,
            'shop_product_id' => $product->id,
            'code' => 'SP'.strtoupper(Str::random(8)),
            'amount' => $product->price,
            'details' => $request->input('details'),
        ]);

        return redirect()->route('shop.index')->with('status', 'Захиалга үүслээ. Гүйлгээний утгад '.$order->code.' кодыг бичнэ үү.');
    }
}
