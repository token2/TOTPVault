<?php
// templates/layout.php — shared head/foot
$appName = 'TOTPVault';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? $appName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
 <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --ink:  #0a0a0b;
  --ink2: #3f3f46;
  --ink3: #71717a;
  --ink4: #a1a1aa;
  --line: #e4e4e7;
  --line2:#f4f4f5;
  --bg:   #ffffff;
  --bg2:  #fafafa;
  --blue: #2563eb;
  --blue-h:#1d4ed8;
  --blue-l:#dbeafe;
  --green:#16a34a;
  --green-l:#dcfce7;
  --red:  #dc2626;
  --red-l:#fee2e2;
  --amber:#d97706;
  --amber-l:#fef3c7;
  --purple:#7c3aed;
  --r:    8px;
  --r2:   12px;
  --r3:   16px;
  --shadow: 0 1px 3px rgba(0,0,0,.08),0 1px 2px rgba(0,0,0,.06);
  --shadow2:0 4px 6px -1px rgba(0,0,0,.07),0 2px 4px -2px rgba(0,0,0,.05);
  --shadow3:0 10px 15px -3px rgba(0,0,0,.07),0 4px 6px -4px rgba(0,0,0,.05);
  --shadow4:0 20px 25px -5px rgba(0,0,0,.07),0 8px 10px -6px rgba(0,0,0,.04);
}
html{font-size:16px;-webkit-font-smoothing:antialiased}
body{font-family:'Instrument Sans',sans-serif;background:var(--bg);color:var(--ink);line-height:1.6}
a{color:inherit;text-decoration:none}
button,input,select,textarea{font-family:inherit}
code,pre,.mono{font-family:'JetBrains Mono',monospace}

/* ── Utilities ── */
.text-xs{font-size:.75rem}.text-sm{font-size:.875rem}.text-base{font-size:1rem}
.text-lg{font-size:1.125rem}.text-xl{font-size:1.25rem}.text-2xl{font-size:1.5rem}
.text-3xl{font-size:1.875rem}.text-4xl{font-size:2.25rem}
.font-medium{font-weight:500}.font-semibold{font-weight:600}.font-bold{font-weight:700}
.text-ink2{color:var(--ink2)}.text-ink3{color:var(--ink3)}.text-ink4{color:var(--ink4)}
.text-blue{color:var(--blue)}.text-green{color:var(--green)}.text-red{color:var(--red)}
.flex{display:flex}.items-center{align-items:center}.justify-between{justify-content:space-between}
.gap-2{gap:.5rem}.gap-3{gap:.75rem}.gap-4{gap:1rem}.gap-6{gap:1.5rem}.gap-8{gap:2rem}
.flex-1{flex:1}.flex-wrap{flex-wrap:wrap}
.hidden{display:none!important}
.truncate{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.w-full{width:100%}

/* ── Buttons ── */
.btn{display:inline-flex;align-items:center;gap:.5rem;padding:.5rem 1rem;border-radius:var(--r);font-size:.875rem;font-weight:500;cursor:pointer;border:none;transition:all .15s;white-space:nowrap;text-decoration:none}
.btn-primary{background:var(--blue);color:#fff}
.btn-primary:hover{background:var(--blue-h)}
.btn-secondary{background:var(--bg);color:var(--ink);border:1px solid var(--line);box-shadow:var(--shadow)}
.btn-secondary:hover{background:var(--bg2)}
.btn-danger{background:var(--red);color:#fff}
.btn-danger:hover{background:#b91c1c}
.btn-ghost{background:transparent;color:var(--ink3)}
.btn-ghost:hover{background:var(--bg2);color:var(--ink)}
.btn-sm{padding:.375rem .75rem;font-size:.8125rem}
.btn-lg{padding:.75rem 1.5rem;font-size:1rem}
.btn-icon{padding:.5rem;border-radius:var(--r)}

/* ── Forms ── */
.field{display:flex;flex-direction:column;gap:.375rem}
.label{font-size:.875rem;font-weight:500;color:var(--ink2)}
.input{width:100%;padding:.5rem .75rem;border:1px solid var(--line);border-radius:var(--r);font-size:.875rem;color:var(--ink);background:var(--bg);transition:border-color .15s,box-shadow .15s;outline:none}
.input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.1)}
.input::placeholder{color:var(--ink4)}
select.input{cursor:pointer}
.hint{font-size:.75rem;color:var(--ink4)}

/* ── Cards ── */
.card{background:var(--bg);border:1px solid var(--line);border-radius:var(--r3);box-shadow:var(--shadow)}
.card-body{padding:1.5rem}

/* ── Badge ── */
.badge{display:inline-flex;align-items:center;padding:.25rem .625rem;border-radius:999px;font-size:.75rem;font-weight:500}
.badge-blue{background:var(--blue-l);color:var(--blue)}
.badge-green{background:var(--green-l);color:var(--green)}
.badge-amber{background:var(--amber-l);color:var(--amber)}

/* ── Toast ── */
#toast-container{position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:.75rem;pointer-events:none}
.toast{display:flex;align-items:center;gap:.75rem;padding:.875rem 1.25rem;background:var(--ink);color:#fff;border-radius:var(--r2);box-shadow:var(--shadow4);font-size:.875rem;pointer-events:auto;animation:toastIn .2s ease;max-width:360px}
.toast.success .toast-icon{color:#4ade80}
.toast.error   .toast-icon{color:#f87171}
@keyframes toastIn{from{opacity:0;transform:translateY(.5rem)}to{opacity:1;transform:none}}
@keyframes toastOut{to{opacity:0;transform:translateY(.5rem)}}

/* ── Modal ── */
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;display:flex;align-items:center;justify-content:center;padding:1rem;animation:fadeIn .15s ease}
.modal{background:var(--bg);border-radius:var(--r3);box-shadow:var(--shadow4);width:100%;max-width:480px;animation:slideUp .2s ease}
.modal-header{padding:1.5rem 1.5rem 0;display:flex;justify-content:space-between;align-items:flex-start}
.modal-body{padding:1.5rem}
.modal-footer{padding:0 1.5rem 1.5rem;display:flex;justify-content:flex-end;gap:.75rem}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
@keyframes slideUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}

/* ── Nav ── */
.nav{height:60px;border-bottom:1px solid var(--line);display:flex;align-items:center;padding:0 1.5rem;gap:1rem;position:sticky;top:0;background:rgba(255,255,255,.95);backdrop-filter:blur(8px);z-index:100}
.nav-brand{font-size:1.125rem;font-weight:700;color:var(--ink);display:flex;align-items:center;gap:.5rem}
.nav-logo{width:28px;height:28px;background:var(--blue);border-radius:6px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.875rem;font-weight:700}
.nav-spacer{flex:1}
.avatar{width:32px;height:32px;border-radius:50%;object-fit:cover;background:var(--blue-l);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:600;color:var(--blue);overflow:hidden;flex-shrink:0}

/* ── Divider ── */
.divider{height:1px;background:var(--line);margin:.5rem 0}

/* ── Empty state ── */
.empty{text-align:center;padding:3rem 1rem}
.empty-icon{font-size:2.5rem;margin-bottom:1rem}
</style>
  <link rel="icon" type="image/png" href="/favicon.png?2">
  <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
</head>
<body>
<div id="toast-container"></div>
