// Bomba con flotador (control ON/OFF por nivel)
(function(){
  const cv = document.getElementById('cv-bomba');
  if(!cv) return;
  const ctx = cv.getContext('2d');

  const nivelEl = document.getElementById('bomba_nivel');
  const encEl   = document.getElementById('bomba_enc');
  const offEl   = document.getElementById('bomba_off');
  const wEl     = document.getElementById('bomba_w');
  const vEl     = document.getElementById('bomba_v');

  function draw(){
    const nivel = Number(nivelEl.value)||0;
    const enc   = Number(encEl.value)||40;
    const off   = Number(offEl.value)||85;
    const W     = Math.max(0, Number(wEl.value)||750);
    const V     = Math.max(1, Number(vEl.value)||120);
    // Lógica de control simple con histéresis
    // Si nivel < enc → encender; si nivel > off → apagar.
    const on = (nivel < enc) ? true : (nivel > off ? false : undefined);

    ctx.clearRect(0,0,cv.width,cv.height);
    ctx.font = '16px system-ui, Arial';
    ctx.fillStyle = '#0f172a';
    ctx.fillText(`Nivel: ${nivel}%`, 20, 28);
    ctx.fillText(`Encender < ${enc}% | Apagar > ${off}%`, 160, 28);

    // Tanque
    const tank = {x:80, y:60, w:140, h:200};
    ctx.strokeStyle = '#374151'; ctx.lineWidth = 2;
    ctx.strokeRect(tank.x, tank.y, tank.w, tank.h);

    // Agua
    const hFill = tank.h * (nivel/100);
    ctx.fillStyle = '#60a5fa';
    ctx.fillRect(tank.x+1, tank.y + tank.h - hFill, tank.w-2, hFill);

    // Bomba
    const pump = {x:360, y:200, r:20};
    ctx.strokeStyle = '#374151';
    ctx.beginPath(); ctx.arc(pump.x, pump.y, pump.r, 0, Math.PI*2); ctx.stroke();
    ctx.font = '14px system-ui'; ctx.fillStyle='#0f172a';
    ctx.fillText('Bomba', pump.x-24, pump.y+42);

    // Flotador
    const flY = tank.y + tank.h - (tank.h*(enc/100));
    ctx.fillStyle = '#f59e0b';
    ctx.fillRect(tank.x + tank.w + 30, flY-8, 16, 16);
    ctx.fillStyle = '#0f172a';
    ctx.fillText('Flotador (umbral ON)', tank.x + tank.w + 10, flY-14);

    // Tuberías y conexión (simple)
    ctx.strokeStyle = '#111827'; ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(tank.x + tank.w, tank.y + tank.h - 10);
    ctx.lineTo(300, tank.y + tank.h - 10);
    ctx.lineTo(300, pump.y);
    ctx.lineTo(pump.x - pump.r, pump.y);
    ctx.stroke();

    // Consumo si ON
    let text = 'Estado: ';
    let onState = false;

    // Decidir ON/OFF final: si on es undefined, mantenemos estado según posición relativa al rango
    if (nivel < enc) onState = true;
    else if (nivel > off) onState = false;
    else {
      // dentro de banda: heurística básica - si agua está bajando simulado por valor < (enc+off)/2, mantenemos
      onState = nivel < (enc + off)/2;
    }

    if (onState){
      // Efecto de succión
      ctx.strokeStyle = '#10b981';
      ctx.lineWidth = 4;
      ctx.beginPath();
      ctx.moveTo(pump.x, pump.y);
      ctx.lineTo(pump.x+60, pump.y);
      ctx.stroke();

      const I = W / V;
      text += `ON — I≈${I.toFixed(2)} A (${W} W @ ${V} V)`;
    } else {
      ctx.strokeStyle = '#9ca3af';
      ctx.lineWidth = 2;
      ctx.beginPath();
      ctx.moveTo(pump.x, pump.y);
      ctx.lineTo(pump.x+60, pump.y);
      ctx.stroke();

      text += 'OFF';
    }

    ctx.fillStyle = onState ? '#16a34a' : '#b91c1c';
    ctx.fillRect(20, 8, 10, 10);
    ctx.fillStyle = '#0f172a';
    ctx.fillText(text, 40, 18);
  }

  [nivelEl, encEl, offEl, wEl, vEl].forEach(el=>el.addEventListener('input', draw));
  draw();
})();
