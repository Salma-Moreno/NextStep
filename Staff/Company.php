<?php 
session_start();
require '../Conexiones/db.php'; // debe definir $conn (mysqli)

// Guardián: Solo Staff
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
    header('Location: StaffLogin.php');
    exit;
}

// Traer compañías y su dirección
$query = "
SELECT 
    c.ID_Company,
    c.Name,
    c.RFC,
    c.Email,
    c.Phone_Number,
    ca.ID_Company_Address,
    ca.Street,
    ca.City,
    ca.State,
    ca.Postal_Code
FROM company c
LEFT JOIN company_address ca 
    ON c.FK_ID_Company_Address = ca.ID_Company_Address
ORDER BY c.Name ASC
";

$res = mysqli_query($conn, $query);
if (!$res) die("Error SQL: " . mysqli_error($conn));

$companies = [];
while ($r = mysqli_fetch_assoc($res)) $companies[] = $r;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Colaboradores - NextStep</title>

<style>
/* -------------------------
   Reset / base
   ------------------------- */
*{box-sizing:border-box}
body{font-family:Arial,Helvetica,sans-serif;background:#f5f7fb;margin:0;color:#1b2430}
.container{width:95%;max-width:1200px;margin:28px auto;padding:12px}
h1{color:#0c71c3;margin:0 0 14px 0}

/* -------------------------
   Controls
   ------------------------- */
.controls-row{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px}
.controls-left{display:flex;gap:8px;align-items:center;width:100%}
#search{flex:1;padding:10px;border-radius:10px;border:1px solid #d7e0ee;font-size:1rem}
.btn{background:#0c71c3;color:#fff;padding:9px 12px;border-radius:10px;border:none;cursor:pointer;font-weight:600}
.btn.secondary{background:#6c757d}

/* -------------------------
   Carrusel compacto (3 tarjetas visibles)
   ------------------------- */
.carousel-shell{position:relative;height:240px;display:flex;align-items:center;justify-content:center;overflow:visible;margin:12px 0}
.cards-viewport{width:100%;max-width:780px;height:220px;position:relative;display:flex;align-items:center;justify-content:center;pointer-events:none}

/* tarjeta base */
.company-preview {
    position: absolute;
    width: 320px;
    height: 200px;
    border-radius: 14px;
    background:#fff;
    box-shadow:0 10px 30px rgba(11,24,48,0.08);
    padding:14px;
    display:flex;
    flex-direction:column;
    gap:8px;
    transition: transform .45s cubic-bezier(.2,.9,.2,1), opacity .35s ease, filter .35s ease;
    cursor:pointer;
    pointer-events:auto;
    overflow:hidden;
}

/* stacking positions (we use transforms for nicer animation) */
.company-preview.left {
    transform: translateX(-220px) scale(.92) rotateY(6deg);
    z-index:10;
    opacity:0.9;
}
.company-preview.center {
    transform: translateX(0) scale(1) translateZ(0);
    z-index:20;
    opacity:1;
}
.company-preview.right {
    transform: translateX(220px) scale(.92) rotateY(-6deg);
    z-index:10;
    opacity:0.9;
}

/* preview text */
.card-title{font-size:18px;color:#0c71c3;margin:0}
.card-sub{font-size:14px;color:#334155;line-height:1.2}

/* faded cards outside visible range: hide smoothly */
.company-preview.hidden {
    transform: scale(.8) translateY(10px);
    opacity:0;
    pointer-events:none;
}

/* -------------------------
   Expanded card (overlay that grows to full-width)
   ------------------------- */
.expanded-overlay {
    position:fixed;
    inset:24px 20px 24px 20px; /* top right bottom left */
    background:#fff;
    border-radius:14px;
    box-shadow:0 18px 60px rgba(11,24,48,0.18);
    z-index:1000;
    padding:20px;
    display:flex;
    flex-direction:column;
    gap:12px;
    transform-origin:left center;
    transition: transform .32s cubic-bezier(.2,.9,.2,1), opacity .25s ease;
    max-height:calc(100vh - 48px);
    overflow:auto;
}

/* when expanded, dim/blur the rest */
.dimmed {
    filter: blur(4px) brightness(.85);
    transition: filter .25s ease, opacity .25s ease;
}

/* content inside expanded */
.expanded-header{display:flex;align-items:center;justify-content:space-between;gap:12px}
.expanded-left{flex:1}
.expanded-title{font-size:22px;color:#0c71c3;margin:0}
.expanded-meta{color:#334155;font-size:14px}

/* details table */
.details-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-top:6px}
.detail-row{background:#f8fafc;padding:10px;border-radius:8px;font-size:14px;color:#253043}
.actions-row{display:flex;gap:10px;justify-content:flex-end;margin-top:12px}

/* -------------------------
   Small responsive tweaks
   ------------------------- */
@media (max-width: 980px){
    .company-preview{width:280px;height:190px}
    .company-preview.left{transform:translateX(-170px) scale(.93)}
    .company-preview.right{transform:translateX(170px) scale(.93)}
    .cards-viewport{max-width:640px}
}
@media (max-width:700px){
    .company-preview{width:86%;left:50%;transform:translateX(-50%)!important;position:relative}
    .cards-viewport{height:auto}
    .carousel-shell{height:auto;padding-bottom:8px}
}
</style>
</head>
<body>
<?php include '../Includes/HeaderMenuStaff.php'; ?>

<div class="container" id="pageRoot">
    <div class="controls-row">
        <div class="controls-left">
            <input id="search" placeholder="Buscar compañía, RFC o ciudad..." oninput="filterCompanies()">
        </div>
        <div>
            <button class="btn" onclick="openAddModal();">+ Agregar compañía</button>
        </div>
    </div>

    <h1>Colaboradores</h1>

    <div class="carousel-shell" aria-label="Carrusel de compañías">
        <button class="btn" style="position:absolute;left:8px;top:50%;transform:translateY(-50%);" onclick="prevCompany()">◂</button>

        <div class="cards-viewport" id="cardsViewport" role="list">
            <!-- tarjetas generadas por JS -->
        </div>

        <button class="btn" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);" onclick="nextCompany()">▸</button>
    </div>
</div>

<!-- EXPANDED OVERLAY (se crea/usa desde JS) -->
<div id="overlayRoot" style="display:none"></div>

<!-- MODAL AGREGAR/EDITAR (mismo formulario que antes) -->
<div id="modalForm" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(4,8,20,0.45);z-index:1200;align-items:center;justify-content:center;" onclick="closeModal(event)">
    <div class="modal" style="background:#fff;border-radius:10px;width:94%;max-width:680px;padding:18px;box-shadow:0 12px 30px rgba(0,0,0,0.25);" onclick="event.stopPropagation();">
        <h2 id="formTitle" style="margin-top:0;color:#0c71c3">Nueva compañía</h2>

        <form id="companyForm" method="post" action="Add_Com.php">
            <input type="hidden" name="company_id" id="company_id" value="">
            <input type="hidden" name="address_id" id="address_id" value="">

            <div style="display:flex;gap:12px;margin-bottom:10px;flex-wrap:wrap">
                <div style="flex:1;min-width:160px">
                    <label style="font-weight:600;font-size:0.9rem">Nombre</label>
                    <input name="name" id="f_name" required style="width:100%;padding:8px;border-radius:8px;border:1px solid #d7e0ee">
                </div>
                <div style="flex:1;min-width:160px">
                    <label style="font-weight:600;font-size:0.9rem">RFC</label>
                    <input name="rfc" id="f_rfc" required style="width:100%;padding:8px;border-radius:8px;border:1px solid #d7e0ee">
                </div>
            </div>

            <div style="display:flex;gap:12px;margin-bottom:10px;flex-wrap:wrap">
                <div style="flex:1;min-width:160px">
                    <label style="font-weight:600;font-size:0.9rem">Email</label>
                    <input name="email" id="f_email" type="email" required style="width:100%;padding:8px;border-radius:8px;border:1px solid #d7e0ee">
                </div>
                <div style="flex:1;min-width:160px">
                    <label style="font-weight:600;font-size:0.9rem">Teléfono</label>
                    <input name="phone" id="f_phone" style="width:100%;padding:8px;border-radius:8px;border:1px solid #d7e0ee">
                </div>
            </div>

            <h3 style="margin:6px 0 8px 0;color:#0c71c3">Dirección</h3>
            <div style="display:flex;gap:12px;margin-bottom:10px;flex-wrap:wrap">
                <div style="flex:2;min-width:160px">
                    <label style="font-weight:600;font-size:0.9rem">Calle</label>
                    <input name="street" id="f_street" required style="width:100%;padding:8px;border-radius:8px;border:1px solid #d7e0ee">
                </div>
                <div style="flex:1;min-width:140px">
                    <label style="font-weight:600;font-size:0.9rem">Ciudad</label>
                    <input name="city" id="f_city" required style="width:100%;padding:8px;border-radius:8px;border:1px solid #d7e0ee">
                </div>
            </div>

            <div style="display:flex;gap:12px;margin-bottom:6px;flex-wrap:wrap">
                <div style="flex:1;min-width:140px">
                    <label style="font-weight:600;font-size:0.9rem">Estado</label>
                    <input name="state" id="f_state" required style="width:100%;padding:8px;border-radius:8px;border:1px solid #d7e0ee">
                </div>
                <div style="flex:1;min-width:120px">
                    <label style="font-weight:600;font-size:0.9rem">Código Postal</label>
                    <input name="postal_code" id="f_postal" required style="width:100%;padding:8px;border-radius:8px;border:1px solid #d7e0ee">
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:12px">
                <button type="button" class="btn secondary" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
/* ---------- Datos desde PHP ---------- */
const companies = <?= json_encode($companies, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;

/* ---------- Estado ---------- */
let visibleIndex = 0;            // índice del centro (visible)
let filtered = [...companies];  // array filtrado
let expandedId = null;          // id expandido (null = ninguno)

/* ---------- Render del carrusel (hasta 3 tarjetas visibles) ---------- */
function renderCarousel() {
    const viewport = document.getElementById('cardsViewport');
    viewport.innerHTML = '';

    // si no hay compañías, mostrar mensaje
    if (filtered.length === 0) {
        const msg = document.createElement('div');
        msg.style.padding = '18px';
        msg.textContent = 'No hay compañías que coincidan.';
        viewport.appendChild(msg);
        return;
    }

    // normalizar visibleIndex dentro del rango del array
    if (visibleIndex < 0) visibleIndex = filtered.length - 1;
    if (visibleIndex >= filtered.length) visibleIndex = 0;

    // calcular indices para left, center, right según longitud
    const center = visibleIndex;
    const left = (center - 1 + filtered.length) % filtered.length;
    const right = (center + 1) % filtered.length;

    // helper crea tarjeta
    function makeCard(item, posClass, extraIndex) {
        const card = document.createElement('article');
        card.className = 'company-preview ' + posClass;
        card.setAttribute('role','listitem');
        card.tabIndex = 0;
        card.innerHTML = `
            <h3 class="card-title">${escapeHtml(item.Name)}</h3>
            <div class="card-sub"><strong>RFC:</strong> ${escapeHtml(item.RFC)}</div>
            <div class="card-sub"><strong>Ciudad:</strong> ${escapeHtml(item.City || '—')}</div>
            <div style="margin-top:auto;font-size:13px;color:#334155"><strong>Email:</strong> ${escapeHtml(item.Email || '—')}</div>
        `;
        card.onclick = (e) => { e.stopPropagation(); toggleExpand(item.ID_Company); };
        card.onkeydown = (e) => { if (e.key === 'Enter') toggleExpand(item.ID_Company); };
        return card;
    }

    // dependiendo de la cantidad de elementos
    if (filtered.length === 1) {
        viewport.appendChild(makeCard(filtered[center],'center'));
    } else if (filtered.length === 2) {
        viewport.appendChild(makeCard(filtered[left],'left'));
        viewport.appendChild(makeCard(filtered[center],'center'));
    } else {
        viewport.appendChild(makeCard(filtered[left],'left'));
        viewport.appendChild(makeCard(filtered[center],'center'));
        viewport.appendChild(makeCard(filtered[right],'right'));
    }

    // if expanded, dim the underlying page
    applyDim();
}

/* ---------- navegación ---------- */
function prevCompany() {
    visibleIndex = (visibleIndex - 1 + filtered.length) % filtered.length;
    renderCarousel();
}
function nextCompany() {
    visibleIndex = (visibleIndex + 1) % filtered.length;
    renderCarousel();
}

/* ---------- search/filter ---------- */
function filterCompanies() {
    const q = (document.getElementById('search').value || '').trim().toLowerCase();
    filtered = companies.filter(c => {
        const text = `${c.Name} ${c.RFC} ${c.City || ''} ${c.Email || ''}`.toLowerCase();
        return text.includes(q);
    });
    visibleIndex = 0;
    renderCarousel();
}

/* ---------- expand / collapse (tipo C: la tarjeta se transforma en overlay full-width) ---------- */
function toggleExpand(id) {
    // si ya abierto y clic en la misma -> cerrar
    if (expandedId === id) {
        closeExpanded();
        return;
    }
    // abrir nuevo
    openExpandedById(id);
}

function openExpandedById(id) {
    const item = companies.find(x => x.ID_Company == id);
    if (!item) return;

    expandedId = id;
    // crear overlay content
    const overlayRoot = document.getElementById('overlayRoot');
    overlayRoot.innerHTML = ''; // limpiar
    overlayRoot.style.display = 'block';

    const overlay = document.createElement('section');
    overlay.className = 'expanded-overlay';
    overlay.tabIndex = 0;
    overlay.innerHTML = `
        <div class="expanded-header">
            <div class="expanded-left">
                <h2 class="expanded-title">${escapeHtml(item.Name)}</h2>
                <div class="expanded-meta"><strong>RFC:</strong> ${escapeHtml(item.RFC || '—')} &nbsp; • &nbsp; <strong>Email:</strong> ${escapeHtml(item.Email || '—')}</div>
            </div>
            <div style="display:flex;gap:10px;align-items:center">
                <button class="btn secondary" onclick="event.stopPropagation(); closeExpanded()">Cerrar</button>
            </div>
        </div>

        <div class="details-grid" style="margin-top:12px">
            <div class="detail-row"><strong>Teléfono</strong><div style="margin-top:6px">${escapeHtml(item.Phone_Number || '—')}</div></div>
            <div class="detail-row"><strong>Código Postal</strong><div style="margin-top:6px">${escapeHtml(item.Postal_Code || '—')}</div></div>
            <div class="detail-row"><strong>Calle</strong><div style="margin-top:6px">${escapeHtml(item.Street || '—')}</div></div>
            <div class="detail-row"><strong>Ciudad / Estado</strong><div style="margin-top:6px">${escapeHtml((item.City||'—') + ' / ' + (item.State||'—'))}</div></div>
        </div>

        <div class="actions-row" style="margin-top:16px">
            <button class="btn" onclick="event.stopPropagation(); openEditModal(${item.ID_Company})">Editar</button>
            <button class="btn secondary" onclick="event.stopPropagation(); confirmDelete(${item.ID_Company})">Eliminar</button>
        </div>
    `;
    // cerrar overlay al click en fondo (pero no al click dentro)
    overlayRoot.appendChild(overlay);

    // atenuar el resto de la página
    applyDim(true);

    // focus accessibility
    overlay.focus();
}

/* close expanded overlay */
function closeExpanded() {
    expandedId = null;
    const overlayRoot = document.getElementById('overlayRoot');
    overlayRoot.innerHTML = '';
    overlayRoot.style.display = 'none';
    applyDim(false);
}

/* Apply dim/blur on page when overlay open */
function applyDim(force = null) {
    const pageRoot = document.getElementById('pageRoot');
    if (force === true || (force === null && expandedId !== null)) {
        pageRoot.classList.add('dimmed');
        // hide carousel viewport interaction while expanded
        document.getElementById('cardsViewport').style.pointerEvents = 'none';
    } else {
        pageRoot.classList.remove('dimmed');
        document.getElementById('cardsViewport').style.pointerEvents = 'auto';
    }
}

/* ---------- Open modal add/edit ---------- */
function openAddModal() {
    document.getElementById('formTitle').textContent = 'Nueva compañía';
    const form = document.getElementById('companyForm');
    form.action = 'Add_Com.php';
    ['company_id','address_id','f_name','f_rfc','f_email','f_phone','f_street','f_city','f_state','f_postal'].forEach(id=>{
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    document.getElementById('modalForm').style.display = 'flex';
}
function openEditModal(id) {
    const c = companies.find(x => x.ID_Company == id);
    if (!c) return;
    document.getElementById('formTitle').textContent = 'Editar compañía';
    const form = document.getElementById('companyForm');
    form.action = 'Editar_Comp.php';
    document.getElementById('company_id').value = c.ID_Company || '';
    document.getElementById('address_id').value = c.ID_Company_Address || '';
    document.getElementById('f_name').value = c.Name || '';
    document.getElementById('f_rfc').value = c.RFC || '';
    document.getElementById('f_email').value = c.Email || '';
    document.getElementById('f_phone').value = c.Phone_Number || '';
    document.getElementById('f_street').value = c.Street || '';
    document.getElementById('f_city').value = c.City || '';
    document.getElementById('f_state').value = c.State || '';
    document.getElementById('f_postal').value = c.Postal_Code || '';
    document.getElementById('modalForm').style.display = 'flex';
}

/* ---------- close modal ---------- */
function closeModal(e) {
    if (!e) {
        document.getElementById('modalForm').style.display = 'none';
        return;
    }
    if (e.target && e.target.classList.contains('modal-backdrop')) {
        document.getElementById('modalForm').style.display = 'none';
    }
}

/* ---------- delete (fetch POST JSON) ---------- */
function confirmDelete(id) {
    if (!confirm('¿Eliminar esta compañía? Esta acción es irreversible.')) return;
    fetch('Eliminar_Com.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({id:id})
    }).then(r=>r.json()).then(data=>{
        if (data && data.success) {
            alert('Compañía eliminada');
            location.reload();
        } else {
            alert('Error: '+(data.error||'No se pudo eliminar'));
        }
    }).catch(e=>{
        alert('Error de red');
    });
}

/* ---------- utilities ---------- */
function escapeHtml(s){
    if (s === null || s === undefined) return '';
    return String(s).replace(/[&<>"']/g, function(m){
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
    });
}

/* ---------- initialize ---------- */
renderCarousel();

/* allow keyboard arrows to navigate carousel */
document.addEventListener('keydown', (e) => {
    if (document.getElementById('modalForm').style.display === 'flex') return; // don't interfere while modal open
    if (e.key === 'ArrowLeft') prevCompany();
    if (e.key === 'ArrowRight') nextCompany();
    if (e.key === 'Escape') {
        // close expanded or modal
        if (expandedId) closeExpanded();
        else if (document.getElementById('modalForm').style.display === 'flex') closeModal();
    }
});
</script>
</body>
</html>
