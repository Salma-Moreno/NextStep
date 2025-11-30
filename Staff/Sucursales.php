<?php
session_start();
require '../Conexiones/db.php';

// Solo Staff
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
    header('Location: StaffLogin.php'); exit;
}

// Consulta sucursales
$query = "
SELECT p.*, c.Name AS CompanyName
FROM collection_point p
LEFT JOIN company c ON p.FK_ID_Company = c.ID_Company
ORDER BY p.Name ASC
";
$res = mysqli_query($conn, $query);
$points = [];
while ($r = mysqli_fetch_assoc($res)) $points[] = $r;

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Sucursales - NextStep</title>

<link rel="stylesheet" href="../assets/Staff/Entregas.css">

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">

</head>

<body>
<?php include '../Includes/HeaderMenuStaff.php'; ?>

<div class="container">

    <h1>Sucursales</h1>

    <div class="branches-wrapper">

        <!-- Tarjeta principal -->
        <div id="branchCard" class="collapsed" onclick="toggleExpand()">

            <!-- Flechas -->
            <button class="arrow-btn arrow-left" onclick="event.stopPropagation(); prevBranch();">‹</button>
            <button class="arrow-btn arrow-right" onclick="event.stopPropagation(); nextBranch();">›</button>

            <!-- Vista previa -->
            <div id="branchHeader"></div>

            <!-- Vista completa -->
            <div id="branchFull"></div>
        </div>

        <!-- MAPA LEAFLET -->
        <div id="map"></div>
    </div>

    <div class="add-btn-area">
        <button class="btn" onclick="openAddModal()">+ Agregar sucursal</button>
    </div>

</div>

<!-- MODAL FORM -->
<div id="modalForm" class="modal-backdrop" onclick="closeModal(event)">
    <div class="modal" onclick="event.stopPropagation();">

        <h2 id="formTitle">Nueva sucursal</h2>

        <form id="branchForm" method="post" action="Add_Point.php">
            <input type="hidden" name="ID_Point" id="f_id">

            <div class="form-row">
                <div class="col">
                    <label>Nombre</label>
                    <input name="Name" id="f_name" required>
                </div>
                <div class="col">
                    <label>Teléfono</label>
                    <input name="Phone_number" id="f_phone">
                </div>
            </div>

            <label>Dirección</label>
            <input name="address" id="f_address" required>

            <div class="form-row">
                <div class="col">
                    <label>Latitud</label>
                    <input name="latitude" id="f_lat">
                </div>
                <div class="col">
                    <label>Longitud</label>
                    <input name="longitude" id="f_lng">
                </div>
            </div>

            <label>Compañía</label>
            <select name="FK_ID_Company" id="f_company" required style="width:100%; padding:7px; border-radius:8px;">
                <option value="">Seleccione...</option>
                <?php
                $q2 = mysqli_query($conn, "SELECT * FROM company ORDER BY Name");
                while ($c = mysqli_fetch_assoc($q2)):
                ?>
                    <option value="<?=$c['ID_Company']?>"><?=$c['Name']?></option>
                <?php endwhile; ?>
            </select>

            <div class="actions">
                <button type="button" class="btn secondary" onclick="closeModal()">Cancelar</button>
                <button class="btn">Guardar</button>
            </div>
        </form>

    </div>
</div>


<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<script>
const points = <?= json_encode($points) ?>;
let index = 0;
let expanded = false;

let map;
let marker;

/* ---------------------------- */
/*  RENDER PRINCIPAL            */
/* ---------------------------- */
function renderBranch() {
    const p = points[index];
    if (!p) return;

    document.getElementById("branchHeader").innerHTML = `
        <h2>${escape(p.Name)}</h2>
        <p><strong>Dirección:</strong> ${escape(p.address)}</p>
        <p><strong>Compañía:</strong> ${escape(p.CompanyName)}</p>
    `;

    document.getElementById("branchFull").innerHTML = `
        <div class="detail-row"><strong>Teléfono:</strong> ${escape(p.Phone_number || "—")}</div>
        <div class="detail-row"><strong>Lat:</strong> ${escape(p.latitude || "—")}</div>
        <div class="detail-row"><strong>Lng:</strong> ${escape(p.longitude || "—")}</div>

        <button class="btn" onclick="event.stopPropagation(); openEdit(${index})">Editar</button>
        <button class="btn secondary" onclick="event.stopPropagation(); deletePoint(${p.ID_Point})">Eliminar</button>
    `;

    updateMap(p.latitude, p.longitude);
}

function escape(s){
    return String(s||"").replace(/[&<>]/g, m => ({
        "&":"&amp;","<":"&lt;",">":"&gt;"
    }[m]));
}

/* ---------------------------- */
/*  EXPANDIR / COLAPSAR         */
/* ---------------------------- */
function toggleExpand(){
    const card = document.getElementById("branchCard");
    expanded = !expanded;
    card.classList.toggle("expanded", expanded);
    card.classList.toggle("collapsed", !expanded);
}

/* ---------------------------- */
/*  NAVEGACIÓN                   */
/* ---------------------------- */
function prevBranch(){
    index = (index - 1 + points.length) % points.length;
    renderBranch();
}
function nextBranch(){
    index = (index + 1) % points.length;
    renderBranch();
}

/* ---------------------------- */
/*  MAPA LEAFLET                */
/* ---------------------------- */
function initMap(){
    map = L.map('map').setView([19.4326, -99.1332], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    marker = L.marker([19.4326, -99.1332]).addTo(map);

    renderBranch();
}

function updateMap(lat, lng){
    if(!lat || !lng) return;

    const pos = [parseFloat(lat), parseFloat(lng)];
    map.setView(pos, 15);
    marker.setLatLng(pos);
}

/* ---------------------------- */
/*  MODAL                       */
/* ---------------------------- */
function openAddModal(){
    document.getElementById("branchForm").action = "Add_Point.php";
    document.getElementById("formTitle").innerText = "Nueva sucursal";
    document.getElementById("modalForm").style.display = "flex";
}

function openEdit(i){
    const p = points[i];

    document.getElementById("branchForm").action = "Edit_Point.php";
    document.getElementById("formTitle").innerText = "Editar sucursal";

    document.getElementById("f_id").value = p.ID_Point;
    document.getElementById("f_name").value = p.Name;
    document.getElementById("f_phone").value = p.Phone_number;
    document.getElementById("f_address").value = p.address;
    document.getElementById("f_lat").value = p.latitude;
    document.getElementById("f_lng").value = p.longitude;
    document.getElementById("f_company").value = p.FK_ID_Company;

    document.getElementById("modalForm").style.display = "flex";
}

function closeModal(){
    document.getElementById("modalForm").style.display = "none";
}

/* ---------------------------- */
/*  ELIMINAR                    */
/* ---------------------------- */
function deletePoint(id){
    if(!confirm("¿Eliminar sucursal?")) return;

    fetch("Delete_Point.php", {
        method:"POST",
        body: JSON.stringify({id})
    })
    .then(r=>r.json())
    .then(d=>{
        if(d.success) location.reload();
        else alert("Error: "+d.error);
    });
}

window.onload = initMap;
</script>

</body>
</html>
