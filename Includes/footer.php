</main>



<!-- Carga el JS principal global del sistema -->
<script src="/nextstep/assets/js/main.js"></script>

<!-- Si la vista define $custom_js, lo carga aquí-->
<?php if (isset($custom_js)): ?>
    <script src="<?php echo $custom_js; ?>"></script>
<?php endif; ?>

</body>
</html>