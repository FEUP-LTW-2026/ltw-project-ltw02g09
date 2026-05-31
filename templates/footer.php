    <footer class="site-footer">
        <div class="container site-footer-inner">
            <div>
                <p>&copy; <?= date('Y') ?> GymFit</p>
                <p class="text-muted">Plataforma de gestão de ginásio</p>
            </div>
            <div class="site-footer-contacts">
                <p class="text-muted">Contactos</p>
                <p>Telefone: +351 912 345 678</p>
                <p>Email: contacto@gymfit.pt</p>
            </div>
        </div>
    </footer>
    <?php if (isset($extra_js)): ?>
        <?php foreach ((array)$extra_js as $js): ?>
            <script src="/js/<?= e($js) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    <script src="/js/main.js"></script>
</body>
</html>
