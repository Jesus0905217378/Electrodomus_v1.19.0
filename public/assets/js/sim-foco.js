// Foco con interruptor simple
(function(){
  const cv = document.getElementById('cv-foco');
  if(!cv) return;
  const ctx = cv.getContext('2d');
  const sw = document.getElementById('foco_switch');

  function draw(on){
    ctx.clearRect(0,0,cv.width,cv.height);

    // Texto
    ctx.font = '16px system-ui, Arial';
    ctx.fillStyle = '#0f172a';
    ctx.fillText('Fase', 40, 40);
    ctx.fillText('Neutro', 40, 280);

    // Barra fase y neutro
    ctx.strokeStyle = '#111827';
    ctx.lineWidth = 2;
    ctx.beginPath(); ctx.moveTo(20,60); ctx.lineTo(cv.width-20,60); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(20,300); ctx.lineTo(cv.width-20,300); ctx.stroke();

    // Interruptor en serie con fase (x=160..220)
    ctx.strokeStyle = '#ef4444'; // fase
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.moveTo(60,60);
    ctx.lineTo(160,60);
    // switch
    if(on){
      ctx.lineTo(220,60); // cerrado
    }else{
      ctx.lineTo(180,40); // abierto
    }
    ctx.stroke();

    // Cable hacia lámpara
    ctx.beginPath();
    ctx.moveTo(220,60);
    ctx.lineTo(220,160);
    ctx.lineTo(420,160);
    ctx.stroke();

    // Lámpara (circulo)
    ctx.strokeStyle = '#f59e0b';
    ctx.lineWidth = 4;
    ctx.beginPath();
    ctx.arc(460,160,24,0,Math.PI*2);
    ctx.stroke();

    // Conexión a neutro
    ctx.strokeStyle = '#2563eb';
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.moveTo(500,160);
    ctx.lineTo(500,300);
    ctx.lineTo(80,300);
    ctx.stroke();

    // Luz encendida (glow)
    if(on){
      const grd = ctx.createRadialGradient(460,160,8,460,160,60);
      grd.addColorStop(0,'rgba(245,158,11,0.8)');
      grd.addColorStop(1,'rgba(245,158,11,0)');
      ctx.fillStyle = grd;
      ctx.beginPath();
      ctx.arc(460,160,60,0,Math.PI*2);
      ctx.fill();
    }

    // Indicador ON/OFF
    ctx.fillStyle = on ? '#16a34a' : '#b91c1c';
    ctx.fillRect(20,12,10,10);
    ctx.fillStyle = '#0f172a';
    ctx.fillText(on?'Circuito cerrado (ON)':'Circuito abierto (OFF)', 40, 22);
  }

  sw.addEventListener('change', ()=>draw(sw.value==='on'));
  draw(false);
})();
