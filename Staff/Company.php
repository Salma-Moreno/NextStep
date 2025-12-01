<?php 
session_start();
require '../Conexiones/db.php'; // Debe definir $conn

// Solo Staff
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
    header('Location: StaffLogin.php');
    exit;
}

// Traer compañías con dirección
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
<link rel="stylesheet" href="../assets/Staff/Comp.css">
</head>
<body>
<?php include '../Includes/HeaderMenuStaff.php'; ?>

<div class="container" id="pageRoot">
    <div class="controls-row">
        <div class="controls-left">
            <input id="search" placeholder="Buscar compañía, RFC o ciudad..." oninput="filterCompanies()">
        </div>
        <div>
            <button class="btn" onclick="openAddModal()">+ Agregar compañía</button>
        </div>
    </div>

    <h1>Colaboradores</h1>

    <div class="carousel-shell" aria-label="Carrusel de compañías">
        <button class="btn nav-left" onclick="prevCompany()">◂</button>
        <div class="cards-viewport" id="cardsViewport" role="list"></div>
        <button class="btn nav-right" onclick="nextCompany()">▸</button>
    </div>
</div>

<!-- Modal -->
<div id="modalForm" class="modal-backdrop" onclick="closeModal(event)">
    <div class="modal" onclick="event.stopPropagation();">
        <h2 id="formTitle">Nueva compañía</h2>
        <form id="companyForm" method="post" action="../Staff/Add_Com.php">
            <input type="hidden" name="company_id" id="company_id">
            <input type="hidden" name="address_id" id="address_id">

            <div class="form-row">
                <div class="form-col">
                    <label>Nombre</label>
                    <input type="text" name="name" id="f_name" required>
                </div>
                <div class="form-col">
                    <label>RFC</label>
                    <input type="text" name="rfc" id="f_rfc" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <label>Email</label>
                    <input type="email" name="email" id="f_email" required>
                </div>
                <div class="form-col">
                    <label>Teléfono</label>
                    <input type="text" name="phone" id="f_phone">
                </div>
            </div>

            <h3>Dirección</h3>
            <div class="form-row">
                <div class="form-col">
                    <label>Calle</label>
                    <input type="text" name="street" id="f_street" required>
                </div>
                <div class="form-col">
                    <label>Ciudad</label>
                    <input type="text" name="city" id="f_city" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-col">
                    <label>Estado</label>
                    <input type="text" name="state" id="f_state" required>
                </div>
                <div class="form-col">
                    <label>Código Postal</label>
                    <input type="text" name="postal_code" id="f_postal" required>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn secondary" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
// ---------- Datos desde PHP ----------
const companies = <?php echo json_encode($companies, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;

// ---------- Estado ----------
let visibleIndex = 0;
let filtered = [...companies];

// ---------- Render carrusel ----------
function renderCarousel() {
    const viewport = document.getElementById('cardsViewport');
    viewport.innerHTML = '';

    if (filtered.length === 0) {
        const msg = document.createElement('div');
        msg.textContent = 'No hay compañías que coincidan.';
        viewport.appendChild(msg);
        return;
    }

    if (visibleIndex < 0) visibleIndex = filtered.length-1;
    if (visibleIndex >= filtered.length) visibleIndex = 0;

    const center = visibleIndex;
    const left = (center-1+filtered.length)%filtered.length;
    const right = (center+1)%filtered.length;

    function makeCard(item, posClass) {
        const card = document.createElement('div');
        card.className = 'company-preview ' + posClass;
        card.tabIndex=0;
        card.innerHTML = `
            <h3>${escapeHtml(item.Name)}</h3>
            <div><strong>RFC:</strong> ${escapeHtml(item.RFC)}</div>
            <div><strong>Ciudad:</strong> ${escapeHtml(item.City || '—')}</div>
            <div style="margin-top:auto;font-size:13px;color:#334155"><strong>Email:</strong> ${escapeHtml(item.Email||'—')}</div>
        `;
        card.onclick = ()=>openEditModal(item.ID_Company);
        return card;
    }

    if (filtered.length === 1) viewport.appendChild(makeCard(filtered[center],'center'));
    else if (filtered.length === 2) {
        viewport.appendChild(makeCard(filtered[left],'left'));
        viewport.appendChild(makeCard(filtered[center],'center'));
    } else {
        viewport.appendChild(makeCard(filtered[left],'left'));
        viewport.appendChild(makeCard(filtered[center],'center'));
        viewport.appendChild(makeCard(filtered[right],'right'));
    }
}

// ---------- Navegación ----------
function prevCompany(){ visibleIndex=(visibleIndex-1+filtered.length)%filtered.length; renderCarousel(); }
function nextCompany(){ visibleIndex=(visibleIndex+1)%filtered.length; renderCarousel(); }

// ---------- Filtrar ----------
function filterCompanies(){
    const q = (document.getElementById('search').value||'').trim().toLowerCase();
    filtered = companies.filter(c=>{
        const text = `${c.Name} ${c.RFC} ${c.City||''} ${c.Email||''}`.toLowerCase();
        return text.includes(q);
    });
    visibleIndex=0;
    renderCarousel();
}

// ---------- Modal ----------
function openAddModal(){
    document.getElementById('formTitle').textContent='Nueva compañía';
    ['company_id','address_id','f_name','f_rfc','f_email','f_phone','f_street','f_city','f_state','f_postal']
        .forEach(id=>document.getElementById(id).value='');
    document.getElementById('modalForm').style.display='flex';
}
function openEditModal(id){
    const c = companies.find(x=>x.ID_Company==id);
    if(!c) return;
    document.getElementById('formTitle').textContent='Editar compañía';
    document.getElementById('company_id').value=c.ID_Company;
    document.getElementById('address_id').value=c.ID_Company_Address||'';
    document.getElementById('f_name').value=c.Name||'';
    document.getElementById('f_rfc').value=c.RFC||'';
    document.getElementById('f_email').value=c.Email||'';
    document.getElementById('f_phone').value=c.Phone_Number||'';
    document.getElementById('f_street').value=c.Street||'';
    document.getElementById('f_city').value=c.City||'';
    document.getElementById('f_state').value=c.State||'';
    document.getElementById('f_postal').value=c.Postal_Code||'';
    document.getElementById('modalForm').style.display='flex';
}
function closeModal(e){
    if(!e || e.target.classList.contains('modal-backdrop')) 
        document.getElementById('modalForm').style.display='none';
}

// ---------- Escape HTML ----------
function escapeHtml(s){
    if(!s) return '';
    return s.replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

// ---------- Inicializar ----------
renderCarousel();
document.addEventListener('keydown',e=>{
    if(document.getElementById('modalForm').style.display==='flex') return;
    if(e.key==='ArrowLeft') prevCompany();
    if(e.key==='ArrowRight') nextCompany();
    if(e.key==='Escape') closeModal();
});
</script>
</body>
</html>
