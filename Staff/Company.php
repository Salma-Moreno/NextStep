<?php 
session_start();
require '../Conexiones/db.php'; // Debe definir $conn

// Solo Staff
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
    header('Location: StaffLogin.php');
    exit;
}

/* ======== MENSAJES FLASH (error / éxito) ======== */
$errorMsg   = $_SESSION['error']   ?? '';
$successMsg = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']); // limpiar para que no se repitan

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

<!-- ======== MENSAJES FLASH EN LA VISTA ======== -->
<?php if ($errorMsg): ?>
    <div class="alert alert-danger">
        <?= htmlspecialchars($errorMsg) ?>
    </div>
<?php endif; ?>

<?php if ($successMsg): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($successMsg) ?>
    </div>
<?php endif; ?>
<!-- =========================================== -->

<div class="container" id="pageRoot">
    <!-- Buscador + botón agregar -->
    <div class="controls-row">
        <div class="controls-left">
            <input id="search" placeholder="Buscar compañía, RFC o ciudad..." oninput="filterCompanies()">
        </div>
        <div>
            <button class="btn" onclick="openAddModal()">+ Agregar compañía</button>
        </div>
    </div>

    <!-- Panel tipo "Solicitud de Beca" -->
    <div class="companies-panel">
        <div class="companies-panel-title">Colaboradores</div>
        <div class="companies-panel-subtitle">
            Selecciona una de las compañías registradas para ver o editar su información.
        </div>

        <!-- Carrusel: flecha izquierda + cards + flecha derecha -->
        <div class="companies-carousel">
            <button id="btnPrev" class="btn nav-arrow" onclick="prevCompanies()">◂</button>

            <!-- Aquí se pintan las tarjetas con JS -->
            <div id="companiesGrid" class="scholarship-list"></div>

            <button id="btnNext" class="btn nav-arrow" onclick="nextCompanies()">▸</button>
        </div>
    </div>
</div>

<!-- Modal formulario agregar/editar compañía -->
<div id="modalForm" class="modal-backdrop" onclick="closeModal(event)">
    <div class="modal" onclick="event.stopPropagation();">
        <h2 id="formTitle">Nueva compañía</h2>
        <form id="companyForm" method="post" action="Add_Com.php">
            <input type="hidden" name="company_id" id="company_id">
            <input type="hidden" name="address_id" id="address_id">

            <div class="form-row">
                <div class="form-col">
                    <label>Nombre *</label>
                    <input type="text" name="name" id="f_name" required>
                </div>
                <div class="form-col">
                    <label>RFC *</label>
                    <input type="text" name="rfc" id="f_rfc" required placeholder="XAXX010101000">
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <label>Email *</label>
                    <input type="email" name="email" id="f_email" required placeholder="contacto@empresa.com">
                </div>
                <div class="form-col">
                    <label>Teléfono (10 dígitos)</label>
                    <input type="text" name="phone" id="f_phone" placeholder="6641234567">
                </div>
            </div>

            <h3>Dirección</h3>
            <div class="form-row">
                <div class="form-col">
                    <label>Calle *</label>
                    <input type="text" name="street" id="f_street" required placeholder="Av. Principal 123">
                </div>
                <div class="form-col">
                    <label>Ciudad *</label>
                    <input type="text" name="city" id="f_city" required placeholder="Tijuana">
                </div>
            </div>
            <div class="form-row">
                <div class="form-col">
                    <label>Estado *</label>
                    <input type="text" name="state" id="f_state" required placeholder="Baja California">
                </div>
                <div class="form-col">
                    <label>Código Postal *</label>
                    <input type="text" name="postal_code" id="f_postal" required maxlength="5" placeholder="22000">
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
let filtered = [...companies];
let currentStart = 0;             // desde qué índice empezamos a mostrar
const CARDS_PER_VIEW = 3;         // cuántas tarjetas se ven a la vez

// ---------- Render GRID de compañías ----------
function renderCompaniesGrid() {
    const grid = document.getElementById('companiesGrid');
    const prevBtn = document.getElementById('btnPrev');
    const nextBtn = document.getElementById('btnNext');

    grid.innerHTML = '';

    if (!filtered.length) {
        const msg = document.createElement('div');
        msg.textContent = 'No hay compañías que coincidan.';
        msg.style.gridColumn = '1 / -1';
        grid.appendChild(msg);

        if (prevBtn) prevBtn.disabled = true;
        if (nextBtn) nextBtn.disabled = true;
        return;
    }

    const visibleCount = Math.min(CARDS_PER_VIEW, filtered.length);

    for (let i = 0; i < visibleCount; i++) {
        const idx = (currentStart + i) % filtered.length;
        const c = filtered[idx];

        const card = document.createElement('div');
        card.className = 'scholarship-card';
        card.onclick = () => openEditModal(c.ID_Company);

        card.innerHTML = `
            <div class="card-image"></div>
            <div class="card-body">
                <h3>${escapeHtml(c.Name)}</h3>
                <div class="card-short-text">
                    <strong>RFC:</strong> ${escapeHtml(c.RFC || '—')}
                </div>
                <div class="card-short-text">
                    <strong>Ciudad:</strong> ${escapeHtml(c.City || '—')}
                </div>
                <div class="card-long-text">
                    <strong>Email:</strong> ${escapeHtml(c.Email || '—')}
                </div>
            </div>
        `;

        grid.appendChild(card);
    }

    const disableNav = filtered.length <= CARDS_PER_VIEW;
    if (prevBtn) prevBtn.disabled = disableNav;
    if (nextBtn) nextBtn.disabled = disableNav;
}

// ---------- Navegación izquierda/derecha ----------
function prevCompanies() {
    if (!filtered.length) return;
    currentStart = (currentStart - 1 + filtered.length) % filtered.length;
    renderCompaniesGrid();
}

function nextCompanies() {
    if (!filtered.length) return;
    currentStart = (currentStart + 1) % filtered.length;
    renderCompaniesGrid();
}

// ---------- Filtrar ----------
function filterCompanies(){
    const q = (document.getElementById('search').value||'').trim().toLowerCase();
    filtered = companies.filter(c=>{
        const text = `${c.Name} ${c.RFC} ${c.City||''} ${c.Email||''}`.toLowerCase();
        return text.includes(q);
    });
    currentStart = 0;  // al filtrar, volvemos al inicio
    renderCompaniesGrid();
}

// ---------- Modal principal (formulario) ----------
function openAddModal(){
    document.getElementById('formTitle').textContent='Nueva compañía';
    ['company_id','address_id','f_name','f_rfc','f_email','f_phone','f_street','f_city','f_state','f_postal']
        .forEach(id=>document.getElementById(id).value='');
    document.getElementById('modalForm').style.display='flex';
    
    // Limpiar errores al abrir modal nuevo
    document.querySelectorAll('.error-message').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.input-error, .input-success').forEach(el => {
        el.classList.remove('input-error', 'input-success');
    });
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
    
    // Limpiar errores al abrir modal de edición
    document.querySelectorAll('.error-message').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.input-error, .input-success').forEach(el => {
        el.classList.remove('input-error', 'input-success');
    });
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

// (Las funciones de validación JS que ya tenías se quedan igual...)

// ---------- Inicializar ----------
renderCompaniesGrid();

// Cerrar modal con ESC si está abierto
document.addEventListener('keydown',e=>{
    if(document.getElementById('modalForm').style.display==='flex' && e.key==='Escape') {
        closeModal();
    }
});
</script>
</body>
</html>
