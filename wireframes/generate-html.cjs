// Generates low-fidelity, grayscale HTML+CSS wireframes for every page in
// resources/views/, one .html file per page, mirroring the structure of the
// real Blade views (sidebar, header, filters, tables, forms) but with
// placeholder content instead of real data.
const fs = require('fs');
const path = require('path');

const OUT_HTML = path.join(__dirname, 'html');
if (!fs.existsSync(OUT_HTML)) fs.mkdirSync(OUT_HTML, { recursive: true });

// ── Sidebar definition (matches resources/views/layouts/navigation.blade.php) ──
const MENU = [
  { section: 'Manajemen Stok', items: [{ key: 'produk', label: 'Produk' }] },
  { section: 'Master Data', items: [
    { key: 'suppliers', label: 'Suppliers' },
    { key: 'pelanggan', label: 'Pelanggan' },
  ]},
  { section: 'Pembelian', items: [
    { key: 'po', label: 'Purchase Orders' },
    { key: 'pembelian', label: 'Pembelian' },
  ]},
  { section: 'Penjualan', items: [
    { key: 'penjualan', label: 'Penjualan', badge: '3' },
    { key: 'pengiriman', label: 'Pengiriman' },
  ]},
  { section: 'Keuangan', items: [
    { key: 'piutang', label: 'Piutang' },
  ]},
  { section: 'Laporan', items: [
    { key: 'lap-penjualan', label: 'Lap. Penjualan' },
    { key: 'lap-pembelian', label: 'Lap. Pembelian' },
    { key: 'lap-piutang', label: 'Lap. Piutang' },
  ]},
  { section: 'Settings', items: [
    { key: 'users', label: 'Users' },
    { key: 'roles', label: 'Role' },
  ]},
];

function sidebarHtml(active) {
  const sections = MENU.map(sec => `
      <div class="section-title">${sec.section}</div>
      ${sec.items.map(it => `
      <div class="menu-item${it.key === active ? ' active' : ''}">${it.label}${it.badge ? `<span class="menu-badge">${it.badge}</span>` : ''}</div>`).join('')}
  `).join('');

  return `
    <aside class="wf-sidebar">
      <div class="logo">TOKO SENTOSA<br><span style="font-weight:400;font-size:11px;">Sistem Inventaris</span></div>
      ${sections}
      <div class="user-box">
        <div style="font-weight:600;color:#333;">Nama User</div>
        <div>user@sentosa.com</div>
        <div style="margin-top:10px;display:flex;gap:8px;">
          <span class="wf-badge">Profile</span>
          <span class="wf-badge">Keluar</span>
        </div>
      </div>
    </aside>`;
}

// ── Block renderers (grayscale, boxy, placeholder content) ─────────────────

const bar = (w = '70%') => `<span class="bar" style="width:${w}"></span>`;

function block(b) {
  switch (b.type) {
    case 'alert':
      return `<div class="wf-alert">${b.text}</div>`;

    case 'filterbar':
      return `<div class="wf-panel"><div class="wf-filterbar">${b.fields.map(f => `
        <div class="wf-field">
          <label>${f.label}</label>
          ${f.kind === 'select'
            ? `<div class="wf-select">${f.placeholder || 'Pilih'} <span class="arrow">&#9662;</span></div>`
            : `<div class="wf-input">${f.placeholder || ''}</div>`}
        </div>`).join('')}
        ${(b.buttons || []).map(t => `<button class="wf-btn${t.primary ? ' primary' : ''}">${t.label}</button>`).join('')}
      </div></div>`;

    case 'table':
      return `<div class="wf-panel" style="padding:0;overflow:hidden;">
        ${b.title ? `<div style="padding:16px 20px;border-bottom:2px solid var(--border-strong);font-weight:600;">${b.title}</div>` : ''}
        <table class="wf-table">
          <thead><tr>${b.columns.map(c => `<th>${c}</th>`).join('')}${b.actions ? '<th>Aksi</th>' : ''}</tr></thead>
          <tbody>
            ${Array.from({ length: b.rows || 3 }).map(() => `<tr>${b.columns.map(() => `<td>${bar(['40%','55%','65%','35%'][Math.floor(Math.random()*4)])}</td>`).join('')}${b.actions ? `<td>${b.actions.map(a => `<span class="wf-link">${a}</span>`).join(' &nbsp;')}</td>` : ''}</tr>`).join('')}
          </tbody>
        </table>
        ${b.footer ? `<div style="padding:12px 20px;border-top:2px solid var(--border-strong);font-weight:600;text-align:right;">${b.footer}</div>` : ''}
        ${b.pagination ? `<div style="padding:12px 20px;color:var(--text-faint);font-size:13px;">&laquo; 1 2 3 &raquo;</div>` : ''}
      </div>`;

    case 'statrow':
      return `<div class="wf-stat-row">${b.stats.map(s => `
        <div class="wf-stat">
          <div class="label">${s.label}</div>
          <div class="value">${s.value}</div>
          ${s.link ? `<div class="wf-link" style="margin-top:8px;">${s.link}</div>` : ''}
        </div>`).join('')}</div>`;

    case 'chart':
      return `<div class="wf-panel"><div style="font-weight:600;margin-bottom:12px;">${b.title}</div><div class="wf-chart" style="height:${b.height || 180}px;">${b.label || 'Chart Placeholder'}</div></div>`;

    case 'image':
      return `<div class="wf-image" style="width:${b.w || '100%'};height:${b.h || '140px'};">${b.label || 'Image'}</div>`;

    case 'twocol':
      return `<div class="wf-two-col" style="${b.ratio ? `grid-template-columns:${b.ratio};` : ''}"><div style="display:flex;flex-direction:column;gap:16px;">${b.left.map(block).join('')}</div><div style="display:flex;flex-direction:column;gap:16px;">${b.right.map(block).join('')}</div></div>`;

    case 'panel':
      return `<div class="wf-panel"><div style="font-weight:600;margin-bottom:14px;">${b.title}</div>${(b.blocks || []).map(block).join('')}</div>`;

    case 'form':
      return `<div class="wf-panel">${b.title ? `<div style="font-weight:600;margin-bottom:14px;">${b.title}</div>` : ''}<div style="display:flex;flex-direction:column;gap:16px;max-width:${b.narrow ? '520px' : '100%'};">
        ${b.fields.map(f => {
          if (f.type === 'file') {
            return `<div class="wf-form-row"><label>${f.label}</label><div class="wf-image" style="width:100px;height:100px;">Image</div><button class="wf-btn">Choose File</button>${f.hint ? `<div class="hint">${f.hint}</div>` : ''}</div>`;
          }
          if (f.type === 'select') {
            return `<div class="wf-form-row"><label>${f.label}</label><div class="wf-select">${f.placeholder || 'Pilih'} <span class="arrow">&#9662;</span></div>${f.hint ? `<div class="hint">${f.hint}</div>` : ''}</div>`;
          }
          if (f.type === 'textarea') {
            return `<div class="wf-form-row"><label>${f.label}</label><div class="wf-textarea" style="height:70px;"></div>${f.hint ? `<div class="hint">${f.hint}</div>` : ''}</div>`;
          }
          return `<div class="wf-form-row"><label>${f.label}</label><div class="wf-input">${f.placeholder || ''}</div>${f.hint ? `<div class="hint">${f.hint}</div>` : ''}</div>`;
        }).join('')}
      </div></div>`;

    case 'itemtable':
      // editable line-item table (PO / purchase / sale detail rows)
      return `<div class="wf-panel" style="padding:0;overflow:hidden;">
        ${b.title ? `<div style="padding:16px 20px;border-bottom:2px solid var(--border-strong);display:flex;justify-content:space-between;align-items:center;"><span style="font-weight:600;">${b.title}</span>${b.addButton ? `<button class="wf-btn primary">${b.addButton}</button>` : ''}</div>` : ''}
        <table class="wf-table">
          <thead><tr>${b.columns.map(c => `<th>${c}</th>`).join('')}</tr></thead>
          <tbody>
            ${Array.from({ length: b.rows || 1 }).map(() => `<tr>${b.columns.map((c, i) => `<td>${i === 0 ? `<div class="wf-select" style="min-width:180px;">Pilih Produk <span class="arrow">&#9662;</span></div>` : (i === b.columns.length - 1 ? `<span class="wf-link">Hapus</span>` : `<div class="wf-input" style="min-width:70px;">${bar('60%')}</div>`)}</td>`).join('')}</tr>`).join('')}
          </tbody>
        </table>
        <div style="padding:12px 20px;border-top:2px solid var(--border-strong);font-weight:600;text-align:right;">${b.total || 'Total: 0'}</div>
      </div>`;

    case 'buttonrow':
      return `<div style="display:flex;gap:12px;justify-content:${b.align || 'flex-end'};">${b.buttons.map(t => `<button class="wf-btn${t.primary ? ' primary' : ''}">${t.label}</button>`).join('')}</div>`;

    case 'text':
      return `<div class="wf-panel">${b.html}</div>`;

    case 'raw':
      return b.html;

    default:
      return '';
  }
}

// ── Page shell ───────────────────────────────────────────────────────────

const CSS_LINK = `<link rel="stylesheet" href="style.css">`;

function adminPage(title, active, headerButtons, blocks) {
  return `<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>${title} — Sentosa Wireframe</title>
${CSS_LINK}
</head>
<body>
  <div class="wf-shell">
    ${sidebarHtml(active)}
    <div class="wf-main">
      <div class="wf-topbar">
        <h1>${title}</h1>
        <div class="actions">${(headerButtons || []).map(t => `<button class="wf-btn${t.primary ? ' primary' : ''}">${t.label}</button>`).join('')}</div>
      </div>
      <div class="wf-content">
        ${blocks.map(block).join('\n')}
      </div>
    </div>
  </div>
</body>
</html>
`;
}

function authPage(title, blocks) {
  return `<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>${title} — Sentosa Wireframe</title>
${CSS_LINK}
</head>
<body>
  <div class="wf-auth-wrap">
    <div class="wf-auth-logo">LOGO</div>
    <div style="font-weight:700;">TOKO SENTOSA</div>
    <div class="wf-auth-card">
      ${blocks.map(block).join('\n')}
    </div>
  </div>
</body>
</html>
`;
}

function storePage(title, navRight, blocks) {
  return `<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>${title} — Sentosa Wireframe</title>
${CSS_LINK}
</head>
<body class="store">
  <div class="wf-store-nav">
    <div class="logo-sm">TOKO SENTOSA</div>
    <div class="actions">${navRight}</div>
  </div>
  <div class="wf-store-content">
    ${blocks.map(block).join('\n')}
  </div>
</body>
</html>
`;
}

// ── CSS ──────────────────────────────────────────────────────────────────

const CSS = `
:root {
  --bg: #ffffff;
  --bg-soft: #f4f4f4;
  --bg-sidebar: #fafafa;
  --border: #d0d0d0;
  --border-strong: #888888;
  --text: #1f1f1f;
  --text-soft: #5a5a5a;
  --text-faint: #9a9a9a;
  --gray-bar: #dcdcdc;
}
* { box-sizing: border-box; }
body {
  font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  font-size: 14px;
  color: var(--text);
  background: var(--bg-soft);
  margin: 0;
}
.wf-shell { display: flex; min-height: 100vh; }
.wf-sidebar {
  width: 250px; flex-shrink: 0;
  background: var(--bg-sidebar);
  border-right: 2px solid var(--border-strong);
  padding: 20px 16px;
}
.wf-sidebar .logo {
  font-weight: 700; font-size: 15px; line-height: 1.5;
  border: 2px solid var(--border-strong); border-radius: 10px;
  padding: 14px; text-align: center; margin-bottom: 22px;
}
.wf-sidebar .section-title {
  font-size: 11px; text-transform: uppercase; letter-spacing: .06em;
  color: var(--text-faint); margin: 18px 0 8px; font-weight: 600;
}
.wf-sidebar .menu-item {
  padding: 9px 12px; border-radius: 7px; margin-bottom: 4px;
  border: 1px solid transparent; color: var(--text);
}
.wf-sidebar .menu-item.active {
  border-color: var(--border-strong); background: #ececec; font-weight: 700;
}
.wf-sidebar .menu-badge {
  float: right; border: 1px solid var(--border-strong); border-radius: 999px;
  padding: 0 7px; font-size: 11px;
}
.wf-sidebar .user-box {
  margin-top: 26px; padding-top: 16px; border-top: 2px solid var(--border);
  font-size: 12px; color: var(--text-soft);
}
.wf-main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.wf-topbar {
  background: var(--bg); border-bottom: 2px solid var(--border-strong);
  padding: 22px 30px; display: flex; align-items: center; justify-content: space-between;
}
.wf-topbar h1 { font-size: 21px; margin: 0; font-weight: 700; }
.wf-topbar .actions { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
.wf-content { padding: 26px 30px; display: flex; flex-direction: column; gap: 20px; }

.wf-btn {
  display: inline-block; border: 2px solid var(--border-strong); border-radius: 8px;
  padding: 9px 18px; font-size: 14px; background: #fff; color: var(--text);
  white-space: nowrap;
}
.wf-btn.primary { background: #333333; color: #ffffff; border-color: #333333; }
.wf-link {
  font-size: 13px; color: var(--text); text-decoration: underline;
  text-decoration-color: var(--border-strong);
}

.wf-panel {
  background: #fff; border: 2px solid var(--border); border-radius: 12px; padding: 22px;
}
.wf-alert {
  border: 2px dashed var(--border-strong); border-radius: 10px; padding: 14px 18px;
  font-size: 13px; color: var(--text-soft); background: #fafafa;
}

.wf-filterbar { display: flex; gap: 14px; flex-wrap: wrap; align-items: flex-end; }
.wf-field { display: flex; flex-direction: column; gap: 6px; }
.wf-field label { font-size: 12px; color: var(--text-soft); font-weight: 600; }
.wf-form-row { display: flex; flex-direction: column; gap: 6px; }
.wf-form-row label { font-size: 13px; color: var(--text-soft); font-weight: 600; }
.hint { font-size: 12px; color: var(--text-faint); }

.wf-input, .wf-select, .wf-textarea {
  border: 2px solid var(--border); border-radius: 8px; padding: 10px 12px;
  min-width: 170px; background: #fdfdfd; color: var(--text-faint); font-size: 14px;
}
.wf-select { display: flex; justify-content: space-between; align-items: center; gap: 8px; cursor: default; }
.wf-select .arrow { color: var(--text-soft); }
.wf-textarea { min-width: 100%; }

.wf-table { width: 100%; border-collapse: collapse; }
.wf-table th {
  background: #eeeeee; text-align: left; padding: 11px 14px; font-size: 12px;
  text-transform: uppercase; letter-spacing: .03em; color: var(--text-soft);
  border-bottom: 2px solid var(--border-strong);
}
.wf-table td { padding: 13px 14px; border-bottom: 1px solid var(--border); font-size: 13px; }
.wf-table tr:last-child td { border-bottom: none; }
.bar { display: inline-block; height: 11px; background: var(--gray-bar); border-radius: 4px; }

.wf-stat-row { display: flex; gap: 16px; flex-wrap: wrap; }
.wf-stat { flex: 1; min-width: 170px; border: 2px solid var(--border); border-radius: 12px; padding: 18px; }
.wf-stat .label { font-size: 12px; color: var(--text-soft); text-transform: uppercase; font-weight: 600; }
.wf-stat .value { font-size: 21px; font-weight: 700; margin-top: 8px; }

.wf-chart, .wf-image {
  border: 2px dashed var(--border-strong); border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  color: var(--text-faint); font-size: 13px; text-align: center;
  background: repeating-linear-gradient(45deg, #f0f0f0, #f0f0f0 12px, #ffffff 12px, #ffffff 24px);
}

.wf-badge {
  display: inline-block; border: 1px solid var(--border-strong); border-radius: 999px;
  padding: 3px 12px; font-size: 12px;
}

.wf-two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start; }

/* Storefront (toko) — no sidebar, centered content */
body.store { background: var(--bg-soft); }
.wf-store-nav {
  background: #fff; border-bottom: 2px solid var(--border-strong);
  padding: 18px 32px; display: flex; align-items: center; justify-content: space-between;
}
.wf-store-nav .logo-sm { font-weight: 700; font-size: 16px; }
.wf-store-nav .actions { display: flex; gap: 14px; align-items: center; font-size: 13px; }
.wf-store-content {
  max-width: 1000px; margin: 0 auto; padding: 26px 24px;
  display: flex; flex-direction: column; gap: 20px;
}
.wf-product-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
.wf-product-card { border: 2px solid var(--border); border-radius: 12px; overflow: hidden; background: #fff; }
.wf-product-card .body { padding: 14px; display: flex; flex-direction: column; gap: 6px; }

/* Auth pages (login etc.) — centered card, no sidebar */
.wf-auth-wrap {
  min-height: 100vh; display: flex; flex-direction: column;
  align-items: center; justify-content: center; gap: 10px;
  background: var(--bg-soft); padding: 40px 20px;
}
.wf-auth-logo {
  width: 72px; height: 72px; border: 2px dashed var(--border-strong); border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  color: var(--text-faint); font-size: 12px;
}
.wf-auth-card {
  width: 100%; max-width: 420px; background: #fff; border: 2px solid var(--border);
  border-radius: 12px; padding: 28px; margin-top: 12px;
  display: flex; flex-direction: column; gap: 18px;
}
.wf-checkbox {
  width: 16px; height: 16px; border: 2px solid var(--border-strong); border-radius: 4px;
  display: inline-block; vertical-align: middle; margin-right: 8px;
}
`;

fs.writeFileSync(path.join(OUT_HTML, 'style.css'), CSS, 'utf8');

module.exports = { adminPage, storePage, authPage, block, MENU };
