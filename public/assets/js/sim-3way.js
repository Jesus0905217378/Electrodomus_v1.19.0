// Circuito 3-way: dos interruptores con viajeros
(function(){
  const cv = document.getElementById('cv-3way');
  if(!cv) return;
  const ctx = cv.getContext('2d');

  const s1 = document.getElementById('w3_s1');
  const s2 = document.getElementById('w3_s2');

  function draw(){
    const S1 = s1.value; // 'up' | 'down'
    const S2 = s2.value;

    ctx.clearRect(0,0,cv.width,cv.height);
    ctx.font = '16px system-ui, Arial';
    ctx.fillStyle = '#0f172a';
    ctx.fillText('Fase', 40, 40);
    ctx.fillText('Neutro', 40, 300);

    // Barras fase y neutro
    ctx.strokeStyle = '#111827'; ctx.lineWidth=2;
    ctx.beginPath(); ctx.moveTo(20,60); ctx.lineTo(cv.width-20,60); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(20,300); ctx.lineTo(cv.width-20,300); ctx.stroke();

    // S1 (común conectado a fase)
    const s1x=180, s2x=400, y=140;
    // Viajeros (líneas intermedias)
    ctx.strokeStyle = '#ef4444'; ctx.lineWidth=3;
    // comunes
    ctx.beginPath(); ctx.moveTo(60,60); ctx.lineTo(s1x,60); ctx.lineTo(s1x,y); ctx.stroke();
    // viajeros entre S1 y S2:
    // dos líneas paralelas
    ctx.strokeStyle = '#f97316';
    ctx.beginPath(); ctx.moveTo(s1x+40,y-20); ctx.lineTo(s2x-40,y-20); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(s1x+40,y+20); ctx.lineTo(s2x-40,y+20); ctx.stroke();

    // S1 palanca
    ctx.strokeStyle='#374151'; ctx.lineWidth=2;
    ctx.strokeRect(s1x, y-30, 40, 60);
    ctx.fillText('S1', s1x+10, y+50);

    // S2 palanca (común a lámpara)
    ctx.strokeRect(s2x-40, y-30, 40, 60);
    ctx.fillText('S2', s2x-30, y+50);

    // Lámpara hacia neutro
    const lampX = 520, lampY = 160;
    ctx.strokeStyle = '#2563eb';
    ctx.beginPath(); ctx.moveTo(s2x, y); ctx.lineTo(lampX-40, lampY); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(lampX+40, lampY); ctx.lineTo(lampX+40, 300); ctx.lineTo(100,300); ctx.stroke();

    ctx.strokeStyle = '#f59e0b'; ctx.lineWidth=4;
    ctx.beginPath(); ctx.arc(lampX, lampY, 20,0,Math.PI*2); ctx.stroke();

    // Lógica circuito: lámpara ON si S1 y S2 conectan por el mismo viajero
    // Convención: S1 'up' conecta común a viajero superior; 'down' al inferior. Igual para S2.
    const pathUpper = (S1==='up' && S2==='up');
    const pathLower = (S1==='down' && S2==='down');
    const on = pathUpper || pathLower;

    if (on){
      const grd = ctx.createRadialGradient(lampX, lampY, 6, lampX, lampY, 60);
      grd.addColorStop(0,'rgba(245,158,11,0.85)');
      grd.addColorStop(1,'rgba(245,158,11,0)');
      ctx.fillStyle = grd;
      ctx.beginPath(); ctx.arc(lampX, lampY, 60, 0, Math.PI*2); ctx.fill();
    }

    ctx.fillStyle = on ? '#16a34a' : '#b91c1c';
    ctx.fillRect(20, 12, 10, 10);
    ctx.fillStyle = '#0f172a';
    ctx.fillText(on?'Lámpara: ON (camino cerrado)':'Lámpara: OFF (camino abierto)', 40, 22);
  }

  [s1,s2].forEach(el=>el.addEventListener('change', draw));
  draw();
})();
