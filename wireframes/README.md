# Wireframes — Sentosa

Low-fidelity, grayscale wireframes generated from the real Blade views in
`resources/views/`. Each page mirrors the structure (sidebar, header,
filters, tables, forms, buttons) of its corresponding Blade file, with
placeholder content instead of real data.

- **Source:** `wireframes/html/*.html` — one static HTML+CSS file per page (shared `style.css`)
- **Screenshots:** `wireframes/png/*.png` — full-page PNG render of each HTML file
- **Generators:** `generate-html.cjs` (layout/CSS/DSL) + `pages.cjs` (per-page content) + `screenshot.cjs` (Playwright screenshot script)

To regenerate after editing the generators:
```
cd wireframes
node pages.cjs
node screenshot.cjs
```

## Pages generated (42)

| # | Page | Source Blade | HTML | PNG |
|---|------|--------------|------|-----|
| 1 | Login | `resources/views/auth/login.blade.php` (+ `layouts/guest.blade.php`) | [html](html/login.html) | [png](png/login.png) |
| 2 | Dashboard | `resources/views/dashboard.blade.php` | [html](html/dashboard.html) | [png](png/dashboard.png) |
| 3 | Produk — List | `resources/views/products/index.blade.php` | [html](html/products-index.html) | [png](png/products-index.png) |
| 4 | Produk — Tambah | `resources/views/products/create.blade.php` | [html](html/products-create.html) | [png](png/products-create.png) |
| 5 | Produk — Ubah | `resources/views/products/edit.blade.php` | [html](html/products-edit.html) | [png](png/products-edit.png) |
| 6 | Supplier — List | `resources/views/suppliers/index.blade.php` | [html](html/suppliers-index.html) | [png](png/suppliers-index.png) |
| 7 | Supplier — Tambah | `resources/views/suppliers/create.blade.php` | [html](html/suppliers-create.html) | [png](png/suppliers-create.png) |
| 8 | Supplier — Ubah | `resources/views/suppliers/edit.blade.php` | [html](html/suppliers-edit.html) | [png](png/suppliers-edit.png) |
| 9 | Pelanggan — List | `resources/views/customers/index.blade.php` | [html](html/customers-index.html) | [png](png/customers-index.png) |
| 10 | Pelanggan — Tambah | `resources/views/customers/create.blade.php` | [html](html/customers-create.html) | [png](png/customers-create.png) |
| 11 | Pelanggan — Ubah | `resources/views/customers/edit.blade.php` | [html](html/customers-edit.html) | [png](png/customers-edit.png) |
| 12 | Purchase Order — List | `resources/views/purchase-orders/index.blade.php` | [html](html/purchase-orders-index.html) | [png](png/purchase-orders-index.png) |
| 13 | Purchase Order — Buat | `resources/views/purchase-orders/create.blade.php` | [html](html/purchase-orders-create.html) | [png](png/purchase-orders-create.png) |
| 14 | Purchase Order — Ubah | `resources/views/purchase-orders/edit.blade.php` | [html](html/purchase-orders-edit.html) | [png](png/purchase-orders-edit.png) |
| 15 | Purchase Order — Detail | `resources/views/purchase-orders/show.blade.php` | [html](html/purchase-orders-show.html) | [png](png/purchase-orders-show.png) |
| 16 | Pembelian — List | `resources/views/purchases/index.blade.php` | [html](html/purchases-index.html) | [png](png/purchases-index.png) |
| 17 | Pembelian — Tambah (terima barang) | `resources/views/purchases/create.blade.php` | [html](html/purchases-create.html) | [png](png/purchases-create.png) |
| 18 | Pembelian — Edit | `resources/views/purchases/edit.blade.php` | [html](html/purchases-edit.html) | [png](png/purchases-edit.png) |
| 19 | Pembelian — Detail | `resources/views/purchases/show.blade.php` | [html](html/purchases-show.html) | [png](png/purchases-show.png) |
| 20 | Penjualan — List | `resources/views/sales/index.blade.php` | [html](html/sales-index.html) | [png](png/sales-index.png) |
| 21 | Penjualan — Tambah | `resources/views/sales/create.blade.php` | [html](html/sales-create.html) | [png](png/sales-create.png) |
| 22 | Penjualan — Edit | `resources/views/sales/edit.blade.php` | [html](html/sales-edit.html) | [png](png/sales-edit.png) |
| 23 | Penjualan — Detail / Invoice | `resources/views/sales/show.blade.php` | [html](html/sales-show.html) | [png](png/sales-show.png) |
| 24 | Pengiriman — List | `resources/views/pengiriman/index.blade.php` | [html](html/pengiriman-index.html) | [png](png/pengiriman-index.png) |
| 25 | Pengiriman — Tambah | `resources/views/pengiriman/create.blade.php` | [html](html/pengiriman-create.html) | [png](png/pengiriman-create.png) |
| 26 | Pengiriman — Edit | `resources/views/pengiriman/edit.blade.php` | [html](html/pengiriman-edit.html) | [png](png/pengiriman-edit.png) |
| 27 | Pengiriman — Detail / Surat Jalan | `resources/views/pengiriman/show.blade.php` | [html](html/pengiriman-show.html) | [png](png/pengiriman-show.png) |
| 28 | Piutang — List | `resources/views/piutang/index.blade.php` | [html](html/piutang-index.html) | [png](png/piutang-index.png) |
| 29 | Piutang — Detail / Bayar | `resources/views/piutang/show.blade.php` | [html](html/piutang-show.html) | [png](png/piutang-show.png) |
| 30 | Laporan Penjualan | `resources/views/laporan/penjualan.blade.php` | [html](html/laporan-penjualan.html) | [png](png/laporan-penjualan.png) |
| 31 | Laporan Pembelian | `resources/views/laporan/pembelian.blade.php` | [html](html/laporan-pembelian.html) | [png](png/laporan-pembelian.png) |
| 32 | Laporan Piutang | `resources/views/laporan/piutang.blade.php` | [html](html/laporan-piutang.html) | [png](png/laporan-piutang.png) |
| 33 | Role — List | `resources/views/roles/index.blade.php` | [html](html/roles-index.html) | [png](png/roles-index.png) |
| 34 | Role — Tambah | `resources/views/roles/create.blade.php` | [html](html/roles-create.html) | [png](png/roles-create.png) |
| 35 | Role — Edit | `resources/views/roles/edit.blade.php` | [html](html/roles-edit.html) | [png](png/roles-edit.png) |
| 36 | User — List | `resources/views/users/index.blade.php` | [html](html/users-index.html) | [png](png/users-index.png) |
| 37 | User — Tambah | `resources/views/users/create.blade.php` | [html](html/users-create.html) | [png](png/users-create.png) |
| 38 | User — Edit | `resources/views/users/edit.blade.php` | [html](html/users-edit.html) | [png](png/users-edit.png) |
| 39 | Toko — Katalog | `resources/views/toko/index.blade.php` | [html](html/toko-index.html) | [png](png/toko-index.png) |
| 40 | Toko — Keranjang | `resources/views/toko/keranjang.blade.php` | [html](html/toko-keranjang.html) | [png](png/toko-keranjang.png) |
| 41 | Toko — Checkout | `resources/views/toko/checkout.blade.php` | [html](html/toko-checkout.html) | [png](png/toko-checkout.png) |
| 42 | Toko — Pesanan Saya | `resources/views/toko/pesanan.blade.php` | [html](html/toko-pesanan.html) | [png](png/toko-pesanan.png) |

## Skipped

- `resources/views/auth/forgot-password.blade.php`, `confirm-password.blade.php`,
  `reset-password.blade.php`, `verify-email.blade.php`, `resources/views/profile/*`,
  `resources/views/components/*`, `resources/views/laporan/pdf/*` — secondary auth
  flows, shared UI components, and print-only PDF templates were left out since
  they aren't primary application screens with distinct layouts to wireframe.
  (The main login screen is included above.)
- The sidebar/navbar (`resources/views/layouts/navigation.blade.php` and
  `layouts/app.blade.php`) is not a separate page — it's wireframed inline as
  part of every admin page above, since that's how it actually appears.

## Design rules used

- Grayscale only (white / grays / black — no brand colors)
- 2px solid borders, 8–12px rounded corners, generous padding
- Base font size 14px, system sans-serif
- Sidebar + content layout matches the real navigation structure and menu order
- Tables/cards/charts/images are boxes with real labels (column headers, stat
  labels, button text) but placeholder *data* (gray bars, dashed chart/image
  boxes) instead of realistic fake values
