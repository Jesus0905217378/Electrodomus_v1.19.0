<?php
// public/simulaciones.php
session_start();
require_once __DIR__.'/../app/helpers.php';
require_once __DIR__.'/../app/auth.php';

$user = auth_user();
if (!$user) { header('Location: '.base_url('login.php')); exit; }

include __DIR__.'/../views/header.php';
include __DIR__.'/../views/nav.php';
?>
<div class="container">
  <div class="card">
    <h2>Simulaciones – Instalaciones Domiciliarias</h2>
    <p class="muted">Selecciona una simulación y usa los controles para ver el comportamiento del circuito.</p>

    <div style="display:flex; gap:8px; flex-wrap:wrap;">
      <button class="btn" data-target="#sim-foco">💡 Foco con interruptor</button>
      <button class="btn" data-target="#sim-toma">🔌 Tomacorriente doble</button>
      <button class="btn" data-target="#sim-bomba">💧 Bomba con flotador</button>
      <button class="btn" data-target="#sim-3way">↕️ Circuito 3-way</button>
    </div>
  </div>

  <!-- Foco con interruptor simple -->
  <div class="card sim" id="sim-foco" style="display:block;">
    <h3>💡 Foco con Interruptor Simple</h3>
    <div style="display:grid; grid-template-columns: 1fr 320px; gap:16px;">
      <canvas id="cv-foco" width="640" height="320" style="width:100%; max-width:640px; border:1px solid #e5e7eb; border-radius:12px;"></canvas>
      <div>
        <p><strong>Controles</strong></p>
        <label class="label">Interruptor:
          <select id="foco_switch" class="input">
            <option value="off">Abierto (OFF)</option>
            <option value="on">Cerrado (ON)</option>
          </select>
        </label>
        <p class="muted">Conexión: Fase → Interruptor → Lámpara → Neutro. La tierra (PE) va a la carcasa (no representada).</p>
      </div>
    </div>
  </div>

  <!-- Tomacorriente doble en paralelo -->
  <div class="card sim" id="sim-toma" style="display:none;">
    <h3>🔌 Tomacorriente Doble (Paralelo)</h3>
    <div style="display:grid; grid-template-columns: 1fr 320px; gap:16px;">
      <canvas id="cv-toma" width="640" height="320" style="width:100%; max-width:640px; border:1px solid #e5e7eb; border-radius:12px;"></canvas>
      <div>
        <p><strong>Controles</strong></p>
        <label class="label">Carga en Toma 1 (W)
          <input type="number" id="toma_w1" class="input" min="0" step="50" value="300">
        </label>
        <label class="label">Carga en Toma 2 (W)
          <input type="number" id="toma_w2" class="input" min="0" step="50" value="600">
        </label>
        <label class="label">Tensión (V)
          <input type="number" id="toma_v" class="input" min="110" step="10" value="120">
        </label>
        <label class="label">Breaker (A)
          <input type="number" id="toma_breaker" class="input" min="10" step="5" value="15">
        </label>
        <p class="muted">Se calcula la corriente total (I = (W1+W2)/V). Si supera la capacidad del breaker, el circuito se abre.</p>
      </div>
    </div>
  </div>

  <!-- Bomba con flotador -->
  <div class="card sim" id="sim-bomba" style="display:none;">
    <h3>💧 Bomba de Agua con Flotador</h3>
    <div style="display:grid; grid-template-columns: 1fr 320px; gap:16px;">
      <canvas id="cv-bomba" width="640" height="320" style="width:100%; max-width:640px; border:1px solid #e5e7eb; border-radius:12px;"></canvas>
      <div>
        <p><strong>Controles</strong></p>
        <label class="label">Nivel del tanque (%)
          <input type="range" id="bomba_nivel" min="0" max="100" value="30" oninput="document.getElementById('bomba_nivel_val').textContent=this.value+'%'">
          <span id="bomba_nivel_val">30%</span>
        </label>
        <label class="label">Umbral encendido flotador (%)
          <input type="number" id="bomba_enc" class="input" min="0" max="100" value="40">
        </label>
        <label class="label">Umbral apagado flotador (%)
          <input type="number" id="bomba_off" class="input" min="0" max="100" value="85">
        </label>
        <label class="label">Potencia bomba (W)
          <input type="number" id="bomba_w" class="input" min="100" step="50" value="750">
        </label>
        <label class="label">Tensión (V)
          <input type="number" id="bomba_v" class="input" min="110" step="10" value="120">
        </label>
        <p class="muted">El flotador cierra el control cuando el nivel está por debajo de “encendido” y abre al superar “apagado”.</p>
      </div>
    </div>
  </div>

  <!-- Circuito 3-way -->
  <div class="card sim" id="sim-3way" style="display:none;">
    <h3>↕️ Circuito 3-Way (Dos Interruptores para una Lámpara)</h3>
    <div style="display:grid; grid-template-columns: 1fr 320px; gap:16px;">
      <canvas id="cv-3way" width="640" height="320" style="width:100%; max-width:640px; border:1px solid #e5e7eb; border-radius:12px;"></canvas>
      <div>
        <p><strong>Controles</strong></p>
        <label class="label">Interruptor 1 (S1)
          <select id="w3_s1" class="input">
            <option value="up">Arriba</option>
            <option value="down">Abajo</option>
          </select>
        </label>
        <label class="label">Interruptor 2 (S2)
          <select id="w3_s2" class="input">
            <option value="up">Arriba</option>
            <option value="down">Abajo</option>
          </select>
        </label>
        <p class="muted">La lámpara enciende cuando los viajeros conectan el común de S1 con el común de S2 por el mismo “camino”.</p>
      </div>
    </div>
  </div>
</div>

<script>
// tabs simples
document.querySelectorAll('button[data-target]').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    document.querySelectorAll('.sim').forEach(s=>s.style.display='none');
    const t = document.querySelector(btn.dataset.target);
    if (t) t.style.display='block';
  });
});
</script>
<script src="<?= htmlspecialchars(base_url('assets/js/sim-foco.js')) ?>"></script>
<script src="<?= htmlspecialchars(base_url('assets/js/sim-tomacorriente.js')) ?>"></script>
<script src="<?= htmlspecialchars(base_url('assets/js/sim-bomba.js')) ?>"></script>
<script src="<?= htmlspecialchars(base_url('assets/js/sim-3way.js')) ?>"></script>
<?php include __DIR__.'/../views/footer.php'; ?>

