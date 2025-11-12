<footer class="footer">© <?= date('Y') ?> ElectroDomus</footer>
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      const sw = '<?= htmlspecialchars(base_url("sw.js")) ?>';
      navigator.serviceWorker.register(sw).catch(console.error);
    });
  }
</script>
<script>
(function(){
  if(!('serviceWorker' in navigator)) return;

  function showUpdateBanner(onConfirm){
    if (document.getElementById('pwa-update-bar')) return;

    const bar = document.createElement('div');
    bar.id = 'pwa-update-bar';
    bar.setAttribute('role','status');
    bar.setAttribute('aria-live','polite');
    bar.innerHTML = `
      <span class="ed-pill">ElectroDomus</span>
      <span>Hay una actualización disponible</span>
      <button id="pwa-update-apply" class="ed-btn ed-btn-apply" aria-label="Aplicar actualización y recargar">Actualizar</button>
      <button id="pwa-update-dismiss" class="ed-btn ed-btn-dismiss" aria-label="Cerrar aviso">Después</button>
    `;
    document.body.appendChild(bar);

    // Enfocar el botón principal para accesibilidad
    const applyBtn = document.getElementById('pwa-update-apply');
    applyBtn && applyBtn.focus();

    applyBtn.addEventListener('click', () => {
      onConfirm && onConfirm();
      bar.remove();
    });
    document.getElementById('pwa-update-dismiss').addEventListener('click', () => {
      bar.remove();
    });
  }

  let refreshing = false;
  navigator.serviceWorker.addEventListener('controllerchange', () => {
    if (refreshing) return;
    refreshing = true;
    // Recarga cuando el nuevo SW toma control
    window.location.reload();
  });

  const swUrl = '<?= htmlspecialchars(base_url("sw.js")) ?>';
  navigator.serviceWorker.register(swUrl).then(reg => {
    // Ya hay uno waiting (p.ej. volviste a la página)
    if (reg.waiting && navigator.serviceWorker.controller) {
      showUpdateBanner(() => reg.waiting.postMessage({ type: 'SKIP_WAITING' }));
    }

    // Detecta uno nuevo durante la sesión
    reg.addEventListener('updatefound', () => {
      const newWorker = reg.installing;
      if (!newWorker) return;
      newWorker.addEventListener('statechange', () => {
        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
          showUpdateBanner(() => newWorker.postMessage({ type: 'SKIP_WAITING' }));
        }
      });
    });
  }).catch(console.error);
})();
</script>


</body>
</html>
