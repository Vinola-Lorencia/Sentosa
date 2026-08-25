const fs = require('fs');
const path = require('path');
const { adminPage, storePage, authPage } = require('./generate-html.cjs');

const OUT = path.join(__dirname, 'html');
const pages = {};

// ── Login ────────────────────────────────────────────────────────────────
pages['login'] = authPage('Login', [
  { type: 'raw', html: `<div class="hint">Session status (opsional, mis. "Password reset link sent")</div>` },
  { type: 'raw', html: `<div class="wf-form-row"><label>Email</label><div class="wf-input"></div></div>` },
  { type: 'raw', html: `<div class="wf-form-row"><label>Password</label><div class="wf-input"></div></div>` },
  { type: 'raw', html: `<div><span class="wf-checkbox"></span>Remember me</div>` },
  { type: 'raw', html: `<div style="display:flex;align-items:center;justify-content:flex-end;gap:16px;"><span class="wf-link">Forgot your password?</span><button class="wf-btn primary">Log in</button></div>` },
]);

// ── Dashboard ────────────────────────────────────────────────────────────
pages['dashboard'] = adminPage('Dashboard', null, [], [
  { type: 'alert', text: '🔔 Notifikasi: 3 Pesanan Baru masuk dari WhatsApp / Toko Online — [Lihat semua] [Detail] [Proses]' },
  { type: 'statrow', stats: [
    { label: 'Penjualan Bulan Ini', value: 'Rp xxx.xxx', link: 'Lihat semua ->' },
    { label: 'Pembelian Bulan Ini', value: 'Rp xxx.xxx', link: 'Lihat semua ->' },
    { label: 'Piutang Outstanding', value: 'Rp xxx.xxx', link: 'Lihat semua ->' },
    { label: 'Stok Menipis', value: 'x produk', link: 'Lihat semua ->' },
  ]},
  { type: 'twocol', ratio: '2fr 1fr', left: [
    { type: 'chart', title: 'Penjualan 6 Bulan Terakhir', label: 'Bar Chart Placeholder', height: 200 },
  ], right: [
    { type: 'table', title: 'Piutang Jatuh Tempo', columns: ['Customer', 'Jatuh Tempo'], rows: 3, footer: 'Lihat semua piutang ->' },
  ]},
  { type: 'twocol', left: [
    { type: 'table', title: 'Transaksi Penjualan Terakhir', columns: ['Invoice', 'Customer', 'Total'], rows: 3, footer: 'Lihat semua penjualan ->' },
  ], right: [
    { type: 'table', title: 'Produk Stok Menipis', columns: ['Produk', 'Stok', 'Unit'], rows: 3, footer: 'Lihat semua produk ->' },
  ]},
]);

// ── Products ─────────────────────────────────────────────────────────────
pages['products-index'] = adminPage('Produk', 'produk', [{ label: '+ Tambah Produk', primary: true }], [
  { type: 'alert', text: 'Pesan sukses tersimpan (session flash)' },
  { type: 'filterbar', fields: [
    { label: 'Cari Produk', placeholder: 'Cari produk...' },
    { label: 'Merek', placeholder: 'Filter merek...' },
    { label: 'Stok', kind: 'select', placeholder: 'Semua / Tersedia / Habis' },
  ], buttons: [{ label: 'Filter' }, { label: 'Atur Ulang' }] },
  { type: 'table', columns: ['Nama Produk', 'Merek', 'Harga Beli', 'Diskon', 'Harga Jual', 'Satuan Jual', 'Satuan Beli', 'Stok'], rows: 4, actions: ['Ubah', 'Hapus'], pagination: true },
]);

pages['products-create'] = adminPage('Create Product', 'produk', [{ label: 'Back' }], [
  { type: 'form', fields: [
    { label: 'Product Name', placeholder: '' },
    { label: 'Brand', placeholder: '' },
    { label: 'Gambar Produk', type: 'file', hint: 'JPG, PNG, WEBP. Maks 2MB.' },
    { label: 'Default Buy Price', placeholder: '0.00' },
    { label: 'Default Buy Discount (%)', placeholder: '0.00' },
    { label: 'Sale Price', placeholder: '' },
    { label: 'Sale Unit', placeholder: '' },
    { label: 'Jumlah per Unit Jual', placeholder: '1', hint: 'Contoh: jika satuan jual = lusin, isi 12.' },
    { label: 'Purchase Unit', placeholder: '' },
    { label: 'Dus to Unit', placeholder: '1' },
    { label: 'Stock', placeholder: '0' },
  ]},
  { type: 'buttonrow', buttons: [{ label: 'Cancel' }, { label: 'Save Product', primary: true }] },
]);

pages['products-edit'] = adminPage('Edit Product', 'produk', [{ label: 'Back' }], [
  { type: 'form', fields: [
    { label: 'Product Name', placeholder: 'terisi' },
    { label: 'Brand', placeholder: 'terisi' },
    { label: 'Gambar Produk (saat ini + opsi hapus)', type: 'file', hint: 'Kosongkan jika tidak ingin mengubah gambar.' },
    { label: 'Default Buy Price', placeholder: 'terisi' },
    { label: 'Default Buy Discount (%)', placeholder: 'terisi' },
    { label: 'Sale Price', placeholder: 'terisi' },
    { label: 'Sale Unit', placeholder: 'terisi' },
    { label: 'Jumlah per Unit Jual', placeholder: 'terisi' },
    { label: 'Purchase Unit', placeholder: 'terisi' },
    { label: 'Dus to Unit', placeholder: 'terisi' },
    { label: 'Stock', placeholder: 'terisi' },
  ]},
  { type: 'buttonrow', buttons: [{ label: 'Cancel' }, { label: 'Update Product', primary: true }] },
]);

// ── Suppliers ────────────────────────────────────────────────────────────
pages['suppliers-index'] = adminPage('Supplier', 'suppliers', [{ label: '+ Tambah Supplier', primary: true }], [
  { type: 'alert', text: 'Pesan sukses tersimpan' },
  { type: 'table', columns: ['Nama', 'Telepon', 'Email'], rows: 4, actions: ['Ubah', 'Hapus'], pagination: true },
]);
pages['suppliers-create'] = adminPage('Tambah Supplier', 'suppliers', [{ label: 'Kembali' }], [
  { type: 'form', narrow: true, fields: [
    { label: 'Nama Supplier', placeholder: '' },
    { label: 'Telepon', placeholder: '' },
    { label: 'Email', placeholder: '' },
    { label: 'Alamat', type: 'textarea' },
  ]},
  { type: 'buttonrow', buttons: [{ label: 'Batal' }, { label: 'Simpan Supplier', primary: true }] },
]);
pages['suppliers-edit'] = adminPage('Ubah Supplier', 'suppliers', [{ label: 'Kembali' }], [
  { type: 'form', narrow: true, fields: [
    { label: 'Nama Supplier', placeholder: 'terisi' },
    { label: 'Telepon', placeholder: 'terisi' },
    { label: 'Email', placeholder: 'terisi' },
    { label: 'Alamat', type: 'textarea' },
  ]},
  { type: 'buttonrow', buttons: [{ label: 'Batal' }, { label: 'Perbarui Supplier', primary: true }] },
]);

// ── Customers ────────────────────────────────────────────────────────────
pages['customers-index'] = adminPage('Pelanggan', 'pelanggan', [{ label: '+ Tambah Pelanggan', primary: true }], [
  { type: 'alert', text: 'Pesan sukses tersimpan' },
  { type: 'table', columns: ['Nama', 'Telepon', 'Alamat'], rows: 4, actions: ['Ubah', 'Hapus'], pagination: true },
]);
pages['customers-create'] = adminPage('Tambah Pelanggan', 'pelanggan', [{ label: 'Kembali' }], [
  { type: 'form', narrow: true, fields: [
    { label: 'Nama Pelanggan', placeholder: '' },
    { label: 'Telepon', placeholder: '' },
    { label: 'Alamat', type: 'textarea' },
  ]},
  { type: 'buttonrow', buttons: [{ label: 'Batal' }, { label: 'Simpan Pelanggan', primary: true }] },
]);
pages['customers-edit'] = adminPage('Ubah Pelanggan', 'pelanggan', [{ label: 'Kembali' }], [
  { type: 'form', narrow: true, fields: [
    { label: 'Nama Pelanggan', placeholder: 'terisi' },
    { label: 'Telepon', placeholder: 'terisi' },
    { label: 'Alamat', type: 'textarea' },
  ]},
  { type: 'buttonrow', buttons: [{ label: 'Batal' }, { label: 'Perbarui Pelanggan', primary: true }] },
]);

// ── Purchase Orders ──────────────────────────────────────────────────────
pages['purchase-orders-index'] = adminPage('Purchase Order', 'po', [{ label: '+ Buat PO', primary: true }], [
  { type: 'alert', text: 'Pesan sukses tersimpan' },
  { type: 'table', columns: ['No. PO', 'Supplier', 'Tanggal', 'Status'], rows: 4, actions: ['Kirim WA', 'Detail', 'Ubah', 'Hapus'], pagination: true },
]);
pages['purchase-orders-create'] = adminPage('Buat Purchase Order', 'po', [{ label: 'Kembali' }], [
  { type: 'form', fields: [
    { label: 'Supplier', type: 'select', placeholder: 'Pilih Supplier' },
    { label: 'Tanggal PO', placeholder: 'yyyy-mm-dd' },
    { label: 'Status', type: 'select', placeholder: 'Pending' },
    { label: 'Catatan', type: 'textarea' },
  ]},
  { type: 'itemtable', title: 'Item PO', addButton: '+ Tambah Item', columns: ['Produk', 'Qty Beli', 'Satuan Beli', 'Kuantitas', 'Satuan', 'Harga/Satuan', 'Diskon %', 'Subtotal', 'Aksi'], rows: 1, total: 'Total Keseluruhan: 0.00' },
  { type: 'buttonrow', buttons: [{ label: 'Batal' }, { label: 'Simpan Purchase Order', primary: true }] },
]);
pages['purchase-orders-edit'] = adminPage('Ubah Purchase Order', 'po', [{ label: 'Kembali' }], [
  { type: 'form', fields: [
    { label: 'Supplier', type: 'select', placeholder: 'terisi' },
    { label: 'Tanggal PO', placeholder: 'terisi' },
    { label: 'Status', type: 'select', placeholder: 'terisi' },
    { label: 'Catatan', type: 'textarea' },
  ]},
  { type: 'itemtable', title: 'Item PO', addButton: '+ Tambah Item', columns: ['Produk', 'Qty Beli', 'Satuan Beli', 'Kuantitas', 'Satuan', 'Harga/Satuan', 'Diskon %', 'Subtotal', 'Aksi'], rows: 2, total: 'Total Keseluruhan: xxx.xxx' },
  { type: 'buttonrow', buttons: [{ label: 'Batal' }, { label: 'Perbarui Purchase Order', primary: true }] },
]);
pages['purchase-orders-show'] = adminPage('Detail Purchase Order', 'po', [{ label: 'Kirim WA', primary: true }], [
  { type: 'panel', title: 'Info PO', blocks: [
    { type: 'raw', html: `<div style="display:flex;flex-direction:column;gap:8px;">${['No. PO','Supplier','Tanggal','Status','Catatan'].map(l => `<div><b>${l}:</b> ${bar()}</div>`).join('')}</div>` },
  ]},
  { type: 'table', columns: ['Produk', 'Qty Beli', 'Satuan Beli', 'Kuantitas', 'Satuan', 'Harga', 'Diskon', 'Subtotal'], rows: 3, footer: 'Total Keseluruhan: Rp xxx.xxx' },
]);

// ── Purchases ────────────────────────────────────────────────────────────
pages['purchases-index'] = adminPage('Pembelian', 'pembelian', [{ label: '+ Create', primary: true }], [
  { type: 'alert', text: 'Pesan sukses tersimpan' },
  { type: 'table', columns: ['#', 'Tanggal', 'PO Number', 'Supplier', 'Total'], rows: 4, actions: ['View', 'Edit', 'Delete'], pagination: true },
]);
pages['purchases-create'] = adminPage('Tambah Pembelian', 'pembelian', [{ label: 'Back' }], [
  { type: 'table', title: 'Pilih Purchase Order (klik PO yang barangnya sudah diterima)', columns: ['PO Number', 'Supplier', 'Tanggal PO', 'Status'], rows: 3, actions: ['Pilih ->'] },
  { type: 'panel', title: 'Form Penerimaan Barang (muncul setelah pilih PO)', blocks: [
    { type: 'raw', html: `<div style="display:flex;gap:24px;margin-bottom:16px;"><div><b>Purchase Order:</b> ${bar('120px')}</div><div><b>Supplier:</b> ${bar('140px')}</div></div>` },
    { type: 'form', fields: [
      { label: 'Tanggal Diterima', placeholder: 'yyyy-mm-dd' },
      { label: 'Notes', type: 'textarea' },
    ]},
    { type: 'itemtable', title: 'Detail Barang Diterima', columns: ['Product', 'PO Qty', 'PO Unit', 'Qty Diterima', 'Unit', 'Harga/Unit', 'Discount %', 'Subtotal'], rows: 2, total: 'Grand Total: Rp xxx.xxx' },
    { type: 'buttonrow', buttons: [{ label: 'Batal' }, { label: 'Terima Barang', primary: true }] },
  ]},
]);
pages['purchases-edit'] = adminPage('Edit Pembelian', 'pembelian', [{ label: 'Back' }], [
  { type: 'raw', html: `<div class="wf-panel"><b>Purchase Order:</b> ${bar('220px')}</div>` },
  { type: 'form', fields: [
    { label: 'Tanggal Pembelian', placeholder: 'terisi' },
    { label: 'Notes', type: 'textarea' },
  ]},
  { type: 'itemtable', title: 'Detail Barang', columns: ['Product', 'Purchase Qty', 'Purchase Unit', 'Qty Diterima', 'Unit', 'Harga/Unit', 'Discount %', 'Subtotal'], rows: 2, total: 'Grand Total: Rp xxx.xxx' },
  { type: 'buttonrow', buttons: [{ label: 'Batal' }, { label: 'Update Pembelian', primary: true }] },
]);
pages['purchases-show'] = adminPage('Detail Pembelian', 'pembelian', [{ label: 'Back' }], [
  { type: 'panel', title: 'Info Pembelian', blocks: [
    { type: 'raw', html: `<div class="wf-two-col">${['No. Pembelian','Purchase Order','Supplier','Tanggal Diterima','Notes','Total Pembelian'].map(l => `<div><div class="hint">${l}</div><div style="font-weight:700;margin-top:4px;">${bar('60%')}</div></div>`).join('')}</div>` },
  ]},
  { type: 'table', columns: ['Product', 'Qty Diterima', 'Unit', 'Harga/Unit', 'Discount', 'Subtotal'], rows: 3, footer: 'Grand Total: Rp xxx.xxx' },
  { type: 'buttonrow', buttons: [{ label: 'Edit' }, { label: 'Delete' }] },
]);

// ── Sales ────────────────────────────────────────────────────────────────
pages['sales-index'] = adminPage('Penjualan', 'penjualan', [{ label: '+ Tambah', primary: true }], [
  { type: 'alert', text: 'Pesan sukses / error (session flash)' },
  { type: 'table', columns: ['#', 'Tanggal', 'Pelanggan', 'Pembayaran', 'Total'], rows: 4, actions: ['Lihat', 'Ubah', 'Hapus'], pagination: true },
]);
pages['sales-create'] = adminPage('Tambah Penjualan', 'penjualan', [{ label: 'Kembali' }], [
  { type: 'form', fields: [
    { label: 'Pelanggan', type: 'select', placeholder: 'Walk-in Customer' },
    { label: 'Tanggal Penjualan', placeholder: 'yyyy-mm-dd' },
    { label: 'Metode Pembayaran', type: 'select', placeholder: 'Cash / Transfer / Utang' },
    { label: 'Status Pembayaran', type: 'select', placeholder: 'Lunas / Belum Bayar' },
    { label: 'Jatuh Tempo (jika Belum Bayar)', placeholder: 'yyyy-mm-dd' },
    { label: 'Catatan', type: 'textarea' },
  ]},
  { type: 'itemtable', title: 'Detail Barang', addButton: '+ Tambah Barang', columns: ['Produk', 'Stok', 'Qty', 'Satuan', 'Harga/Satuan', 'Diskon %', 'Subtotal', 'Aksi'], rows: 1, total: 'Total Keseluruhan: Rp 0' },
  { type: 'buttonrow', buttons: [{ label: 'Batal' }, { label: 'Simpan Penjualan', primary: true }] },
]);
pages['sales-edit'] = adminPage('Edit Penjualan', 'penjualan', [{ label: 'Kembali' }], [
  { type: 'form', fields: [
    { label: 'Pelanggan', type: 'select', placeholder: 'terisi' },
    { label: 'Tanggal Penjualan', placeholder: 'terisi' },
    { label: 'Metode Pembayaran', type: 'select', placeholder: 'terisi' },
    { label: 'Status Pembayaran', type: 'select', placeholder: 'terisi' },
    { label: 'Catatan', type: 'textarea' },
  ]},
  { type: 'itemtable', title: 'Detail Barang', addButton: '+ Tambah Barang', columns: ['Produk', 'Stok', 'Qty', 'Satuan', 'Harga/Satuan', 'Diskon %', 'Subtotal', 'Aksi'], rows: 2, total: 'Grand Total: Rp xxx.xxx' },
  { type: 'buttonrow', buttons: [{ label: 'Batal' }, { label: 'Update Penjualan', primary: true }] },
]);
pages['sales-show'] = adminPage('Detail Penjualan', 'penjualan', [{ label: 'Cetak Invoice' }, { label: 'Kembali' }], [
  { type: 'panel', title: 'Invoice Header', blocks: [
    { type: 'raw', html: `<div style="display:flex;justify-content:space-between;"><div><b>TOKO SENTOSA</b><br><span class="hint">Alamat toko, telepon</span></div><div style="text-align:right;"><b>INVOICE</b><br><span class="hint">No. Invoice, Tanggal, Metode Bayar</span></div></div>` },
  ]},
  { type: 'raw', html: `<div class="wf-panel"><div class="hint">Info Pelanggan</div><div style="font-weight:700;">${bar('220px')}</div></div>` },
  { type: 'raw', html: `<div class="wf-badge">Status Pembayaran</div>` },
  { type: 'table', columns: ['Produk', 'Qty', 'Unit', 'Harga', 'Disc', 'Subtotal'], rows: 3, footer: 'Total Keseluruhan: Rp xxx.xxx' },
  { type: 'raw', html: `<div class="wf-two-col" style="text-align:center;"><div><div class="hint">Penerima</div><div style="border-top:2px solid var(--border-strong);margin-top:40px;padding-top:8px;">${bar('50%')}</div></div><div><div class="hint">Hormat Kami</div><div style="border-top:2px solid var(--border-strong);margin-top:40px;padding-top:8px;">Toko Sentosa</div></div></div>` },
  { type: 'buttonrow', buttons: [{ label: 'Ubah' }, { label: 'Hapus' }] },
]);

// ── Pengiriman ───────────────────────────────────────────────────────────
pages['pengiriman-index'] = adminPage('Pengiriman', 'pengiriman', [{ label: '+ Tambah Pengiriman', primary: true }], [
  { type: 'alert', text: 'Pesan sukses tersimpan' },
  { type: 'table', columns: ['#', 'No Invoice', 'Penerima', 'Tanggal', 'Tipe', 'Ekspedisi', 'Status'], rows: 4, actions: ['Detail', 'Edit', 'Hapus'], pagination: true },
]);
pages['pengiriman-create'] = adminPage('Tambah Pengiriman', 'pengiriman', [{ label: 'Back' }], [
  { type: 'form', fields: [
    { label: 'Transaksi Penjualan', type: 'select', placeholder: 'Pilih Transaksi' },
    { label: 'Tanggal Pengiriman', placeholder: 'yyyy-mm-dd' },
    { label: 'Tipe Pengiriman', type: 'select', placeholder: 'Ambil Sendiri / Dikirim' },
    { label: 'Status', type: 'select', placeholder: 'Pending / Dikirim' },
    { label: 'Nama Penerima', placeholder: '' },
    { label: 'No. HP Penerima', placeholder: '' },
    { label: 'Alamat Pengiriman (muncul jika Dikirim)', type: 'textarea' },
    { label: 'Ekspedisi', placeholder: 'JNE, J&T, SiCepat, dll' },
    { label: 'Ongkos Kirim', placeholder: '0' },
    { label: 'Jumlah Colly', placeholder: '1', hint: 'Jumlah kardus/karung/koli yang dikirim' },
    { label: 'Catatan', type: 'textarea' },
  ]},
  { type: 'buttonrow', buttons: [{ label: 'Batal' }, { label: 'Simpan Pengiriman', primary: true }] },
]);
pages['pengiriman-edit'] = adminPage('Edit Pengiriman', 'pengiriman', [{ label: 'Back' }], [
  { type: 'raw', html: `<div class="wf-panel"><div class="hint">Transaksi Penjualan</div><div style="font-weight:700;">${bar('260px')}</div></div>` },
  { type: 'form', fields: [
    { label: 'Tanggal Pengiriman', placeholder: 'terisi' },
    { label: 'Tipe Pengiriman', type: 'select', placeholder: 'terisi' },
    { label: 'Status', type: 'select', placeholder: 'terisi' },
    { label: 'Nama Penerima', placeholder: 'terisi' },
    { label: 'No. HP Penerima', placeholder: 'terisi' },
    { label: 'Alamat Pengiriman', type: 'textarea' },
    { label: 'Ekspedisi', placeholder: 'terisi' },
    { label: 'Ongkos Kirim', placeholder: 'terisi' },
    { label: 'Jumlah Colly', placeholder: 'terisi' },
    { label: 'Catatan', type: 'textarea' },
  ]},
  { type: 'buttonrow', buttons: [{ label: 'Batal' }, { label: 'Update Pengiriman', primary: true }] },
]);
pages['pengiriman-show'] = adminPage('Detail Pengiriman', 'pengiriman', [{ label: 'Print Surat Jalan' }, { label: 'Back' }], [
  { type: 'panel', title: 'Surat Jalan Header', blocks: [
    { type: 'raw', html: `<div style="display:flex;justify-content:space-between;"><div><b>TOKO SENTOSA</b><br><span class="hint">Alamat toko, telepon</span></div><div style="text-align:right;"><b>SURAT JALAN</b><br><span class="hint">No, Tanggal, Ref Invoice</span></div></div>` },
  ]},
  { type: 'twocol', left: [
    { type: 'raw', html: `<div class="wf-panel"><div class="hint">Kepada</div><div style="font-weight:700;">${bar('70%')}</div><div class="hint" style="margin-top:8px;">${bar('90%')}</div></div>` },
  ], right: [
    { type: 'raw', html: `<div class="wf-panel"><div class="hint">Info Pengiriman</div>Tipe / Ekspedisi / Ongkir / Status / Jumlah Colly</div>` },
  ]},
  { type: 'table', columns: ['No', 'Produk', 'Qty', 'Unit', 'Keterangan'], rows: 2 },
  { type: 'raw', html: `<div class="wf-two-col" style="grid-template-columns:1fr 1fr 1fr;text-align:center;">${['Pengirim','Kurir','Penerima'].map(l => `<div><div class="hint">${l}</div><div style="border-top:2px solid var(--border-strong);margin-top:40px;padding-top:8px;">${bar('60%')}</div></div>`).join('')}</div>` },
  { type: 'buttonrow', buttons: [{ label: 'Edit' }, { label: 'Hapus' }] },
]);

// ── Piutang ──────────────────────────────────────────────────────────────
pages['piutang-index'] = adminPage('Daftar Piutang', 'piutang', [], [
  { type: 'alert', text: 'Pesan sukses tersimpan' },
  { type: 'statrow', stats: [
    { label: 'Total Piutang', value: 'Rp xxx.xxx' },
    { label: 'Belum Lunas', value: 'x transaksi' },
    { label: 'Sudah Lunas', value: 'x transaksi' },
  ]},
  { type: 'table', columns: ['No Invoice', 'Customer', 'Jumlah', 'Jatuh Tempo', 'Status'], rows: 4, actions: ['Detail', 'Tandai Lunas', 'Hapus'], pagination: true },
  { type: 'panel', title: 'Modal "Tandai Lunas" (muncul saat tombol diklik)', blocks: [
    { type: 'form', narrow: true, fields: [
      { label: 'Customer / Jumlah', placeholder: 'ditampilkan' },
      { label: 'Tanggal Pembayaran', placeholder: 'yyyy-mm-dd' },
    ]},
    { type: 'buttonrow', buttons: [{ label: 'Batal' }, { label: 'Konfirmasi Lunas', primary: true }] },
  ]},
]);
pages['piutang-show'] = adminPage('Detail Piutang', 'piutang', [{ label: 'Back' }], [
  { type: 'panel', title: 'Informasi Piutang', blocks: [
    { type: 'raw', html: `<div class="wf-two-col">${['No Invoice','Customer','Tanggal Penjualan','Jatuh Tempo','Status'].map(l => `<div><div class="hint">${l}</div><div style="font-weight:700;margin-top:4px;">${bar('60%')}</div></div>`).join('')}</div>` },
    { type: 'statrow', stats: [
      { label: 'Total Piutang', value: 'Rp xxx.xxx' },
      { label: 'Sudah Dibayar', value: 'Rp xxx.xxx' },
      { label: 'Sisa', value: 'Rp xxx.xxx' },
    ]},
    { type: 'raw', html: `<div class="hint">Progress bar pembayaran</div><div style="height:10px;border:1px solid var(--border-strong);border-radius:6px;overflow:hidden;"><div style="width:40%;height:100%;background:#ccc;"></div></div>` },
  ]},
  { type: 'form', title: 'Catat Pembayaran', fields: [
    { label: 'Jumlah Bayar', placeholder: 'Rp', hint: 'Maks: Rp xxx.xxx' },
    { label: 'Tanggal Bayar', placeholder: 'yyyy-mm-dd' },
    { label: 'Catatan', placeholder: 'Opsional' },
  ]},
  { type: 'buttonrow', buttons: [{ label: 'Simpan Pembayaran', primary: true }] },
  { type: 'table', title: 'Histori Pembayaran', columns: ['#', 'Tanggal', 'Jumlah', 'Catatan'], rows: 2, footer: 'Total Dibayar: Rp xxx.xxx' },
  { type: 'table', title: 'Detail Barang', columns: ['Produk', 'Qty', 'Unit', 'Harga', 'Disc', 'Subtotal'], rows: 2, footer: 'Grand Total: Rp xxx.xxx' },
]);

// ── Laporan ──────────────────────────────────────────────────────────────
pages['laporan-penjualan'] = adminPage('Laporan Penjualan', 'lap-penjualan', [], [
  { type: 'filterbar', fields: [
    { label: 'Dari Tanggal', placeholder: 'yyyy-mm-dd' },
    { label: 'Sampai Tanggal', placeholder: 'yyyy-mm-dd' },
  ], buttons: [{ label: 'Filter' }, { label: 'Export Excel' }, { label: 'Export PDF' }] },
  { type: 'statrow', stats: [
    { label: 'Total Transaksi', value: 'xx' },
    { label: 'Total Penjualan', value: 'Rp xxx.xxx' },
    { label: 'Rata-rata per Transaksi', value: 'Rp xxx.xxx' },
  ]},
  { type: 'table', title: 'Penjualan per Customer', columns: ['Customer', 'Jumlah Transaksi', 'Total Penjualan', '%'], rows: 3, footer: 'Grand Total: Rp xxx.xxx (100%)' },
  { type: 'table', title: 'Detail Transaksi Penjualan', columns: ['No Invoice', 'Tanggal', 'Customer', 'Metode', 'Status', 'Total'], rows: 3, footer: 'Grand Total: Rp xxx.xxx' },
]);
pages['laporan-pembelian'] = adminPage('Laporan Pembelian', 'lap-pembelian', [], [
  { type: 'filterbar', fields: [
    { label: 'Dari Tanggal', placeholder: 'yyyy-mm-dd' },
    { label: 'Sampai Tanggal', placeholder: 'yyyy-mm-dd' },
  ], buttons: [{ label: 'Filter' }, { label: 'Export Excel' }, { label: 'Export PDF' }] },
  { type: 'statrow', stats: [
    { label: 'Total Transaksi', value: 'xx' },
    { label: 'Total Pembelian', value: 'Rp xxx.xxx' },
  ]},
  { type: 'table', title: 'Omset per Supplier', columns: ['Supplier', 'Jumlah Transaksi', 'Total Pembelian', '%'], rows: 3, footer: 'Grand Total: Rp xxx.xxx (100%)' },
  { type: 'table', title: 'Detail Transaksi Pembelian', columns: ['No', 'Tanggal', 'Supplier', 'No PO', 'Total'], rows: 3, footer: 'Grand Total: Rp xxx.xxx' },
]);
pages['laporan-piutang'] = adminPage('Laporan Piutang', 'lap-piutang', [], [
  { type: 'filterbar', fields: [
    { label: 'Status', kind: 'select', placeholder: 'Semua / Lunas / Belum Lunas' },
  ], buttons: [{ label: 'Filter' }, { label: 'Export Excel' }, { label: 'Export PDF' }] },
  { type: 'statrow', stats: [
    { label: 'Total Piutang', value: 'Rp xxx.xxx' },
    { label: 'Sudah Dibayar', value: 'Rp xxx.xxx' },
    { label: 'Sisa Piutang', value: 'Rp xxx.xxx' },
  ]},
  { type: 'table', columns: ['No Invoice', 'Customer', 'Total', 'Dibayar', 'Sisa', 'Jatuh Tempo', 'Status'], rows: 4, footer: 'Grand Total: Rp xxx.xxx' },
]);

// ── Roles ────────────────────────────────────────────────────────────────
pages['roles-index'] = adminPage('Roles', 'roles', [{ label: '+ New Role', primary: true }], [
  { type: 'alert', text: 'Pesan sukses tersimpan' },
  { type: 'table', columns: ['Role'], rows: 3, actions: ['Edit', 'Delete'] },
]);
pages['roles-create'] = adminPage('Create Role', 'roles', [{ label: 'Back' }], [
  { type: 'form', narrow: true, fields: [{ label: 'Role Name', placeholder: '' }] },
  { type: 'buttonrow', buttons: [{ label: 'Cancel' }, { label: 'Save Role', primary: true }] },
]);
pages['roles-edit'] = adminPage('Edit Role', 'roles', [{ label: 'Back' }], [
  { type: 'form', narrow: true, fields: [{ label: 'Role Name', placeholder: 'terisi' }] },
  { type: 'buttonrow', buttons: [{ label: 'Cancel' }, { label: 'Update Role', primary: true }] },
]);

// ── Users ────────────────────────────────────────────────────────────────
pages['users-index'] = adminPage('Users', 'users', [{ label: '+ New User', primary: true }], [
  { type: 'alert', text: 'Pesan sukses tersimpan' },
  { type: 'table', columns: ['Name', 'Email', 'Role'], rows: 3, actions: ['Edit', 'Delete'] },
]);
pages['users-create'] = adminPage('Create User', 'users', [{ label: 'Back' }], [
  { type: 'form', narrow: true, fields: [
    { label: 'Name', placeholder: '' },
    { label: 'Email', placeholder: '' },
    { label: 'Role', type: 'select', placeholder: 'Select role' },
    { label: 'Password', placeholder: '' },
    { label: 'Confirm Password', placeholder: '' },
  ]},
  { type: 'buttonrow', buttons: [{ label: 'Cancel' }, { label: 'Save User', primary: true }] },
]);
pages['users-edit'] = adminPage('Edit User', 'users', [{ label: 'Back' }], [
  { type: 'form', narrow: true, fields: [
    { label: 'Name', placeholder: 'terisi' },
    { label: 'Email', placeholder: 'terisi' },
    { label: 'Role', type: 'select', placeholder: 'terisi' },
    { label: 'Password', placeholder: '', hint: 'Leave blank to keep current password.' },
    { label: 'Confirm Password', placeholder: '' },
  ]},
  { type: 'buttonrow', buttons: [{ label: 'Cancel' }, { label: 'Update User', primary: true }] },
]);

// ── Toko (storefront) ───────────────────────────────────────────────────
function productCard() {
  return `<div class="wf-product-card">
    <div class="wf-image" style="height:120px;border-radius:0;border-width:0 0 2px 0;">Image</div>
    <div class="body">
      <div style="font-weight:600;">${bar('90%')}</div>
      <div class="hint">${bar('50%')}</div>
      <div style="font-weight:700;">${bar('60%')}</div>
      <div class="hint">${bar('70%')}</div>
      <div class="wf-input" style="width:70px;display:inline-block;">1</div>
      <button class="wf-btn primary" style="width:100%;margin-top:6px;">+ Keranjang</button>
    </div>
  </div>`;
}
pages['toko-index'] = storePage('Toko Sentosa', '<button class="wf-btn primary">Keranjang (2)</button> Nama User <button class="wf-btn">Logout</button>', [
  { type: 'raw', html: `<div class="wf-panel"><div class="wf-filterbar"><div class="wf-field"><label>Cari Produk</label><div class="wf-input" style="min-width:300px;">Cari produk...</div></div><button class="wf-btn primary">Cari</button><button class="wf-btn">Reset</button></div></div>` },
  { type: 'raw', html: `<div class="wf-product-grid">${Array.from({ length: 8 }).map(productCard).join('')}</div>` },
  { type: 'raw', html: `<div class="hint" style="text-align:center;">&laquo; 1 2 3 &raquo;</div>` },
]);
pages['toko-keranjang'] = storePage('Keranjang Belanja', '<span class="hint">&larr; Lanjut Belanja</span>&nbsp;&nbsp;Nama User', [
  { type: 'table', columns: ['Produk', 'Qty', 'Harga', 'Subtotal'], rows: 3, actions: ['Hapus'], footer: 'Grand Total: Rp xxx.xxx' },
  { type: 'buttonrow', buttons: [{ label: 'Lanjut Checkout ->', primary: true }] },
]);
pages['toko-checkout'] = storePage('Checkout', '<span class="hint">&larr; Kembali ke Keranjang</span>&nbsp;&nbsp;Nama User', [
  { type: 'twocol', left: [
    { type: 'form', title: 'Info Pengiriman', fields: [
      { label: 'Nama Pemesan', placeholder: '' },
      { label: 'Alamat Pengiriman', type: 'textarea', hint: 'Isi jika ingin dikirim, kosongkan jika ambil sendiri' },
      { label: 'Metode Pembayaran', type: 'select', placeholder: 'Cash / Transfer / Utang' },
      { label: 'Catatan', type: 'textarea' },
    ]},
    { type: 'buttonrow', buttons: [{ label: 'Kirim Pesanan', primary: true }] },
  ], right: [
    { type: 'panel', title: 'Ringkasan Pesanan', blocks: [
      { type: 'table', columns: ['Produk', 'Qty x Harga'], rows: 3 },
      { type: 'raw', html: `<div style="display:flex;justify-content:space-between;font-weight:700;margin-top:12px;border-top:2px solid var(--border-strong);padding-top:12px;"><span>Total</span><span>Rp xxx.xxx</span></div>` },
    ]},
  ]},
]);
pages['toko-pesanan'] = storePage('Pesanan Saya', '<span class="hint">&larr; Lanjut Belanja</span>&nbsp;&nbsp;Nama User', [
  { type: 'alert', text: 'Pesanan berhasil dibuat! (session flash, ditandai border hijau pada pesanan terbaru)' },
  { type: 'panel', title: 'INV-xxx — 17 Juli 2026 — Rp xxx.xxx — (Status Bayar)', blocks: [
    { type: 'table', columns: ['Produk', 'Qty x Unit', 'Subtotal'], rows: 2 },
  ]},
  { type: 'panel', title: 'INV-xxx — 16 Juli 2026 — Rp xxx.xxx — (Status Bayar)', blocks: [
    { type: 'table', columns: ['Produk', 'Qty x Unit', 'Subtotal'], rows: 1 },
  ]},
]);

// helper used above (declared after use via hoisting is not available for const, so define first)
function bar(w) { return `<span class="bar" style="width:${w || '70%'};display:inline-block;"></span>`; }

// ── Write ────────────────────────────────────────────────────────────────
let count = 0;
for (const [name, html] of Object.entries(pages)) {
  fs.writeFileSync(path.join(OUT, `${name}.html`), html, 'utf8');
  count++;
}
console.log(`Wrote ${count} HTML wireframe pages to ${OUT}`);
