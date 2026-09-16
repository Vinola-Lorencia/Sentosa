<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ImageCompressionService;
use App\Services\SupabaseStorageService;
use App\Services\UnitConverter;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private SupabaseStorageService $supabaseStorage,
        private ImageCompressionService $imageCompression,
    ) {
    }

    public function index(Request $request)
    {
        $query = Product::query();

        if ($request->filled('search')) {
            $query->whereRaw('LOWER(product_name) LIKE ?', ['%' . strtolower($request->search) . '%']);
        }

        if ($request->filled('brand')) {
            $query->whereRaw('LOWER(brand) LIKE ?', ['%' . strtolower($request->brand) . '%']);
        }

        if ($request->stock == 'available') {
            $query->where('stock', '>', 0);
        }

        if ($request->stock == 'empty') {
            $query->where('stock', '<=', 0);
        }

        $products = $query->latest()
            ->paginate(10)
            ->withQueryString();

        return view('products.index', compact('products'));
    }

    public function create()
    {
        return view('products.create');
    }

    public function myProducts(Request $request)
    {
        $now = now();

        $query = Product::where('created_by', auth()->id())
            ->latest('created_at');

        $filter = $request->get('filter', 'all');

        if ($filter === 'today') {
            $query->whereDate('created_at', $now->toDateString());
        } elseif ($filter === 'week') {
            $query->whereBetween('created_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()]);
        } elseif ($filter === 'month') {
            $query->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year);
        }

        $products = $query->paginate(15)->withQueryString();

        $totalBulanIni = Product::where('created_by', auth()->id())
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();

        $totalKeseluruhan = Product::where('created_by', auth()->id())->count();

        return view('products.my-products', compact('products', 'totalBulanIni', 'totalKeseluruhan', 'filter'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_name'         => ['required', 'string', 'max:255', 'unique:products,product_name'],
            'stock_qty'            => ['required', 'numeric', 'min:0'],
            'stock_unit'           => ['required', 'in:Buah,Lusin,Kodi,Gross'],
            // 10MB cap on the ORIGINAL upload — compression happens after
            // validation passes (see storeProductPhoto()), so this only
            // needs to be generous enough for raw high-res phone camera
            // photos, not the final stored size.
            'image'                => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'brand'                => ['nullable', 'string', 'max:255'],
            'default_buy_price'    => ['nullable', 'numeric', 'min:0'],
            'default_buy_discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sale_price'           => ['nullable', 'numeric', 'min:0'],
            'sale_unit'            => ['nullable', 'string'],
            'purchase_unit'        => ['nullable', 'string'],
            'dus_to_unit'          => ['nullable', 'numeric', 'min:0'],
        ], [
            'product_name.unique' => 'Produk dengan nama ini sudah terdaftar. Periksa kembali sebelum menambahkan.',
        ]);

        // Optional fields are NOT NULL (or should be a sane default rather
        // than a bare empty string) in the products table, so a blank
        // submission needs to be coerced here rather than left for the DB.
        $data['default_buy_price']    = $this->blankToDefault($data['default_buy_price'] ?? null, 0);
        $data['default_buy_discount'] = $this->blankToDefault($data['default_buy_discount'] ?? null, 0);
        $data['sale_price']           = $this->blankToDefault($data['sale_price'] ?? null, 0);
        $data['sale_unit']            = $this->blankToDefault($data['sale_unit'] ?? null, 'Buah');
        $data['purchase_unit']        = $this->blankToDefault($data['purchase_unit'] ?? null, 'Goni');
        $data['dus_to_unit']          = $this->blankToDefault($data['dus_to_unit'] ?? null, 1);

        // stock is always stored in Buah — convert the entered qty+unit
        // using the same UNIT_TO_BASE table the rest of the app uses.
        $factor = UnitConverter::UNIT_TO_BASE[strtolower($data['stock_unit'])] ?? 1;
        $data['stock'] = (float) $data['stock_qty'] * $factor;
        unset($data['stock_qty'], $data['stock_unit']);

        unset($data['image']);

        $data['created_by'] = auth()->id();

        // Upload gambar ke Supabase Storage
        if ($request->hasFile('image')) {
            $data['image_path'] = $this->storeProductPhoto($request->file('image'));
        }

        Product::create($data);

        return redirect()
            ->route('products.index')
            ->with('success', 'Product created successfully.');
    }

    public function checkProductName(Request $request)
    {
        $name = trim($request->input('name', ''));

        if ($name === '') {
            return response()->json(['found' => false, 'matches' => []]);
        }

        $existing = Product::whereRaw(
            'LOWER(product_name) LIKE ?',
            ['%' . strtolower($name) . '%']
        )->limit(3)->get(['product_name']);

        return response()->json([
            'found'   => $existing->isNotEmpty(),
            'matches' => $existing->pluck('product_name'),
        ]);
    }

    public function byKaryawan(int $userId)
    {
        // Covers both activity types this karyawan can have on a product:
        // creating it (created_by) and uploading/replacing its photo
        // (updated_by) — including old pre-existing products they didn't
        // create. A product they both created and later re-photographed
        // still appears once (OR, not UNION of two lists).
        $products = Product::where('created_by', $userId)
            ->orWhere('updated_by', $userId)
            ->latest('updated_at')
            ->get(['id', 'product_name', 'stock', 'created_at', 'updated_at', 'created_by', 'updated_by']);

        return response()->json([
            'products' => $products->map(function ($product) use ($userId) {
                // If this user's most recent action on the product was the
                // photo update (updated_by = them), show that; otherwise it's
                // their "added it" entry. When they didn't create the row
                // themselves (created_by is someone else, or null for legacy
                // GM-seeded rows), a matching updated_by is unambiguously a
                // photo activity — no need to compare timestamps at all,
                // which also sidesteps both the NULL created_at crash (bulk
                // DB::table()->insert() never sets it) and second-precision
                // timestamp ties when a product is created and immediately
                // photo-updated within the same second.
                $isPhotoActivity = $product->updated_by === $userId
                    && (
                        $product->created_by !== $userId
                        || $product->created_at === null
                        || $product->updated_at?->gt($product->created_at)
                    );

                $activityAt = $isPhotoActivity ? $product->updated_at : $product->created_at;

                return [
                    'id'           => $product->id,
                    'product_name' => $product->product_name,
                    'stock'        => (float) $product->stock,
                    'stok_display' => $product->stok_display,
                    'activity'     => $isPhotoActivity ? 'Mengubah Foto' : 'Menambah Produk',
                    'created_at'   => $activityAt?->toWib()->format('d M Y H:i') ?? '-',
                    'edit_url'     => route('products.edit', $product->id),
                ];
            }),
        ]);
    }

    public function edit(Product $product)
    {
        return view('products.edit', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'product_name'         => ['required', 'string', 'max:255'],
            'sale_price'           => ['required', 'numeric', 'min:0'],
            'brand'                => ['nullable', 'string', 'max:255'],
            'image'                => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'default_buy_price'    => ['required', 'numeric', 'min:0'],
            'default_buy_discount' => ['required', 'numeric', 'min:0'],
            'sale_unit'            => ['required', 'string', 'max:50'],
            'purchase_unit'        => ['required', 'string', 'max:50'],
            'dus_to_unit'          => ['required', 'numeric', 'min:0.01'],
            'stock_qty'            => ['required', 'numeric', 'min:0'],
            'stock_unit'           => ['required', 'in:Buah,Lusin,Kodi,Gross'],
        ]);

        // stock is always stored in Buah — same conversion as store().
        $factor = UnitConverter::UNIT_TO_BASE[strtolower($data['stock_unit'])] ?? 1;
        $data['stock'] = (float) $data['stock_qty'] * $factor;
        unset($data['stock_qty'], $data['stock_unit']);

        unset($data['image']);

        // Upload gambar baru ke Supabase Storage — hapus yang lama
        if ($request->hasFile('image')) {
            $data['image_path'] = $this->storeProductPhoto($request->file('image'), $product->image_path);
        }

        // Hapus gambar kalau dicentang
        if ($request->boolean('remove_image')) {
            if ($product->image_path) {
                $this->supabaseStorage->delete($product->image_path);
            }
            $data['image_path'] = null;
        }

        $product->update($data);

        return redirect()
            ->route('products.index')
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        // Cek apakah produk sudah pernah ditransaksikan
        if ($product->saleDetails()->exists() || $product->purchaseDetails()->exists()) {
            return redirect()->route('products.index')
                ->with('error', 'Produk tidak dapat dihapus karena sudah tercatat dalam transaksi penjualan.');
        }

        // Hapus gambar saat produk dihapus
        if ($product->image_path) {
            $this->supabaseStorage->delete($product->image_path);
        }

        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('success', 'Product deleted successfully.');
    }

    public function updatePhoto(Request $request, Product $product)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $product->update([
            'image_path' => $this->storeProductPhoto($request->file('image'), $product->image_path),
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'success'    => true,
            'image_url'  => $this->supabaseStorage->getPublicUrl($product->image_path),
        ]);
    }

    private function blankToDefault(mixed $value, mixed $default): mixed
    {
        return $value === null || $value === '' ? $default : $value;
    }

    private function storeProductPhoto($file, ?string $oldPath = null): string
    {
        if ($oldPath) {
            $this->supabaseStorage->delete($oldPath);
        }

        // Resize/recompress before it ever reaches Supabase — always JPEG
        // out, so the extension here no longer follows the original upload.
        $compressed = $this->imageCompression->compress($file);

        $path = 'products/' . uniqid('product_', true) . '.jpg';

        return $this->supabaseStorage->upload($compressed, $path);
    }
}