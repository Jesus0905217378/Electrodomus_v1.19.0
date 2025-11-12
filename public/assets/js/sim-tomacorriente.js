// Tomacorriente doble en paralelo con verificación de breaker
(function(){
  const cv = document.getElementById('cv-toma');
  if(!cv) return;
  const ctx = cv.getContext('2d');

  const w1 = document.getElementById('toma_w1');
  const w2 = document.getElementById('toma_w2');
  const v  = document.getElementById('toma_v');
  const br = document.getElementById('toma_breaker');

  function draw(){
    const W1 = Math.max(0, Number(w1.value)||0);
    const W2 = Math.max(0, Number(w2.value)||0);
    const V  = Math.max(1, Number(v.value)||120);
    const B  = Math.max(1, Number(br.value)||15);
    const Itot = (W1 + W2) / V; // A

    ctx.clearRect(0,0,cv.width,cv.height);

    ctx.font = '16px system-ui, Arial';
    ctx.fillStyle = '#0f172a';
    ctx.fillText(`Tensión: ${V} V`, 20, 24);
    ctx.fillText(`Breaker: ${B} A`, 200, 24);
    ctx.fillText(`Carga total: ${W1+W2} W`, 380, 24);
    ctx.fillText(`Corriente estimada: ${Itot.toFixed(2)} A`, 20, 46);

    const trip = Itot > B;

    // Barras
    ctx.strokeStyle = '#111827'; ctx.lineWidth = 2;
    ctx.beginPath(); ctx.moveTo(20,80); ctx.lineTo(cv.width-20,80); ctx.stroke(); // fase
    ctx.beginPath(); ctx.moveTo(20,300); ctx.lineTo(cv.width-20,300); ctx.stroke(); // neutro

    // Breaker
    ctx.strokeStyle = trip ? '#b91c1c' : '#16a34a';
    ctx.lineWidth = 6;
    ctx.beginPath(); ctx.moveTo(60,80); ctx.lineTo(120,80); ctx.stroke();
    ctx.font = '14px system-ui'; ctx.fillStyle = trip ? '#b91c1c' : '#16a34a';
    ctx.fillText(trip?'Breaker disparado':'Breaker cerrado', 50, 65);

    // Toma 1
    const t1x = 260, t2x = 420;
    ctx.strokeStyle = '#ef4444'; ctx.lineWidth = 3; // fase
    // conexión T1
    if (!trip){ ctx.beginPath(); ctx.moveTo(120,80); ctx.lineTo(t1x,80); ctx.lineTo(t1x,160); ctx.stroke(); }
    ctx.strokeStyle = '#2563eb'; // neutro
    ctx.beginPath(); ctx.moveTo(t1x,160); ctx.lineTo(t1x,300); ctx.stroke();

    // Toma 2
    ctx.strokeStyle = '#ef4444';
    if (!trip){ ctx.beginPath(); ctx.moveTo(120,80); ctx.lineTo(t2x,80); ctx.lineTo(t2x,160); ctx.stroke(); }
    ctx.strokeStyle = '#2563eb';
    ctx.beginPath(); ctx.moveTo(t2x,160); ctx.lineTo(t2x,300); ctx.stroke();

    // Dibujo tomas
    function drawOutlet(x, watts){
      ctx.strokeStyle = '#374151'; ctx.lineWidth = 2;
      ctx.strokeRect(x-20,130,40,60);
      ctx.beginPath();
      ctx.arc(x-8,160,4,0,Math.PI*2);
      ctx.arc(x+8,160,4,0,Math.PI*2);
      ctx.stroke();
      ctx.fillStyle = '#0f172a'; ctx.font='14px system-ui';
      ctx.fillText(`${watts} W`, x-30, 210);
    }
    drawOutlet(t1x, W1);
    drawOutlet(t2x, W2);

    // Estado
    ctx.fillStyle = trip ? '#b91c1c' : '#16a34a';
    ctx.fillRect(20, 58, 10, 10);
    ctx.fillStyle = '#0f172a';
    ctx.fillText(trip ? 'Sobrecorriente: circuito abierto' : 'Dentro de la capacidad del breaker', 40, 68);
  }

  [w1,w2,v,br].forEach(el=>el.addEventListener('input', draw));
  draw();
})();
