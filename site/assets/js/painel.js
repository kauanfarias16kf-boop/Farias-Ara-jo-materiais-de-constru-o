// Pequenos ajudantes do painel.
(() => {
  // modo claro / escuro, lembrado neste aparelho
  document.querySelectorAll('[data-tema-botao]').forEach(b => b.addEventListener('click', () => {
    const r = document.documentElement;
    const novo = r.dataset.tema === 'escuro' ? 'claro' : 'escuro';
    r.dataset.tema = novo;
    try { localStorage.setItem('fa-tema', novo); } catch (e) {}
  }));

  // menu do celular
  const abre = document.querySelector('[data-abre-menu]');
  const veu = document.querySelector('[data-fecha-menu]');
  function menu(aberto) {
    document.body.classList.toggle('menu-aberto', aberto);
    if (veu) veu.hidden = !aberto;
    if (abre) abre.setAttribute('aria-expanded', String(aberto));
    if (aberto) document.querySelector('.lateral .menu a')?.focus();
  }
  abre?.addEventListener('click', () => menu(!document.body.classList.contains('menu-aberto')));
  veu?.addEventListener('click', () => menu(false));
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && document.body.classList.contains('menu-aberto')) { menu(false); abre?.focus(); } });

  // diárias: realça a linha marcada e mostra o total do dia
  const ponto = document.querySelector('.ponto');
  if (ponto) {
    const total = document.querySelector('#total-dia');
    const conta = () => {
      let n = 0, v = 0;
      ponto.querySelectorAll('.pessoa').forEach(p => {
        const f = parseFloat(p.querySelector('input[type=radio]:checked')?.value || '0');
        p.classList.toggle('marcado', f > 0);
        n += f; v += f * parseFloat(p.dataset.diaria || '0');
      });
      if (total) total.textContent = (n ? String(n).replace('.', ',') : '0') + (n === 1 ? ' diária' : ' diárias') + ' · ' + v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    };
    ponto.addEventListener('change', conta);
    document.querySelector('[data-todos]')?.addEventListener('click', () => {
      ponto.querySelectorAll('.pessoa input[value="1"]').forEach(i => (i.checked = true)); conta();
    });
    conta();
  }

  // ---------- diárias: abas ----------
  const abas = document.querySelector('.abas[role=tablist]');
  if (abas) {
    const tabs = [...abas.querySelectorAll('[role=tab]')];
    const mostra = id => {
      tabs.forEach(t => { const on = t.id === id; t.setAttribute('aria-selected', String(on)); document.getElementById(t.getAttribute('aria-controls')).hidden = !on; });
      try { localStorage.setItem('fa-diarias-aba', id); } catch (e) {}
    };
    tabs.forEach(t => t.addEventListener('click', () => mostra(t.id)));
    let ini = abas.dataset.vista ? 'aba-' + abas.dataset.vista : null;
    if (!ini) { try { ini = localStorage.getItem('fa-diarias-aba'); } catch (e) {} }
    if (!ini) ini = innerWidth < 860 ? 'aba-dia' : 'aba-grade';
    mostra(ini);
  }

  // ---------- diárias: grade que salva no toque ----------
  const plan = document.getElementById('planilha');
  if (plan && plan.dataset.travada !== '1') {
    const brl = v => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    const dias = v => (Math.round(v * 10) / 10).toString().replace('.', ',');
    const seletorObra = document.getElementById('obra-grade');
    const salvo = document.getElementById('salvo');
    const PROX = { '0': '1', '1': '0.5', '0.5': '0' };
    const ROT = { '1': 'D', '0.5': '½', '0': '' };
    const NOME = { '1': 'diária', '0.5': 'meia diária', '0': 'não trabalhou' };
    function recalcula() {
      let total = 0;
      const porCol = [];
      plan.querySelectorAll('tbody tr').forEach(tr => {
        let d = 0, v = 0;
        tr.querySelectorAll('.cel').forEach((c, i) => {
          const f = parseFloat(c.dataset.v);
          d += f; v += f * parseFloat(c.dataset.valor) + (f > 0 ? parseFloat(c.dataset.extra || 0) : 0);
          porCol[i] = (porCol[i] || 0) + f;
        });
        tr.querySelector('.t-dias').textContent = dias(d);
        tr.querySelector('.t-val').textContent = brl(v);
        total += v;
      });
      plan.querySelectorAll('tfoot .c-tot').forEach((td, i) => (td.textContent = porCol[i] ? dias(porCol[i]) : ''));
      document.getElementById('g-total').textContent = brl(total);
      const rv = document.getElementById('r-valor'); if (rv) rv.textContent = brl(total);
      const rd = document.getElementById('r-dias'); if (rd) rd.textContent = dias(porCol.reduce((a, b) => a + (b || 0), 0));
    }
    let tSalvo;
    plan.addEventListener('click', async e => {
      const c = e.target.closest('.cel');
      if (!c || c.disabled) return;
      const antes = c.dataset.v, depois = PROX[antes];
      const nome = c.closest('tr').querySelector('.quem-col b').textContent;
      const aplica = v => { c.dataset.v = v; c.querySelector('i').textContent = ROT[v]; c.setAttribute('aria-label', nome + ', ' + c.dataset.dia.split('-').reverse().join('/') + ': ' + NOME[v]); recalcula(); };
      aplica(depois);
      c.classList.add('salvando'); c.classList.remove('erro');
      const fd = new FormData();
      fd.append('acao', 'celula'); fd.append('csrf', plan.dataset.csrf);
      fd.append('f', c.closest('tr').dataset.f); fd.append('dia', c.dataset.dia); fd.append('v', depois);
      fd.append('o', seletorObra ? seletorObra.value : '');
      try {
        const r = await fetch('diarias.php', { method: 'POST', body: fd });
        const j = await r.json();
        if (!j.ok) throw new Error(j.erro || 'erro');
        if (salvo) { salvo.classList.add('mostra'); clearTimeout(tSalvo); tSalvo = setTimeout(() => salvo.classList.remove('mostra'), 1400); }
      } catch (err) {
        aplica(antes); c.classList.add('erro');
        if (salvo) { salvo.textContent = 'Não salvou: ' + (err.message || 'sem conexão'); salvo.style.color = 'var(--erro)'; salvo.classList.add('mostra'); setTimeout(() => { salvo.textContent = '✓ Salvo'; salvo.style.color = ''; salvo.classList.remove('mostra'); }, 3500); }
      } finally { c.classList.remove('salvando'); }
    });
  }

  // ---------- gráfico: dica ao passar o mouse ou tocar ----------
  document.querySelectorAll('.grafico').forEach(g => {
    const dica = document.createElement('div'); dica.className = 'dica-graf'; dica.hidden = true; g.appendChild(dica);
    const barras = [...g.querySelectorAll('.barra')];
    const mostra = a => {
      const r = a.getBoundingClientRect(), gr = g.getBoundingClientRect();
      dica.innerHTML = ''; const b = document.createElement('b'); b.textContent = a.dataset.v; const s = document.createElement('span'); s.textContent = a.dataset.t; dica.append(b, s);
      dica.style.left = (r.left - gr.left + r.width / 2) + 'px'; dica.style.top = (r.top - gr.top + 8) + 'px'; dica.hidden = false;
    };
    g.querySelectorAll('.alvo').forEach(a => {
      a.addEventListener('mouseenter', () => mostra(a));
      a.addEventListener('click', () => mostra(a));
      a.addEventListener('mouseleave', () => (dica.hidden = true));
    });
  });

  // Confirmação nomeada antes de apagar
  document.addEventListener('submit', e => {
    const f = e.target;
    if (f.dataset.confirma && !confirm(f.dataset.confirma)) e.preventDefault();
  });

  // Copiar link (ex.: link de acompanhamento da obra)
  document.querySelectorAll('[data-copiar]').forEach(b => {
    b.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(b.dataset.copiar);
        const t = b.textContent; b.textContent = 'Copiado';
        setTimeout(() => (b.textContent = t), 1600);
      } catch { prompt('Copie o link:', b.dataset.copiar); }
    });
  });

  // Editor de itens do orçamento
  const tabela = document.querySelector('#itens');
  if (!tabela) return;
  const corpo = tabela.querySelector('tbody');
  const modelo = document.querySelector('#modelo-item');
  const num = s => {
    s = String(s || '').trim();
    if (s.includes(',')) s = s.replace(/\./g, '').replace(',', '.');
    const v = parseFloat(s.replace(/[^0-9.\-]/g, ''));
    return isFinite(v) ? v : 0;
  };
  const brl = v => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  function recalcular() {
    let soma = 0;
    corpo.querySelectorAll('tr').forEach(tr => {
      const q = num(tr.querySelector('[name="q[]"]').value);
      const v = num(tr.querySelector('[name="v[]"]').value);
      const t = q * v; soma += t;
      tr.querySelector('.c-t').textContent = brl(t);
    });
    const desc = num(document.querySelector('[name=desconto]').value);
    document.querySelector('#subtotal').textContent = brl(soma);
    document.querySelector('#desc-v').textContent = brl(desc);
    document.querySelector('#total').textContent = brl(Math.max(0, soma - desc));
  }
  function addLinha(desc = '', un = 'un', q = '1', v = '') {
    const tr = modelo.content.firstElementChild.cloneNode(true);
    tr.querySelector('[name="d[]"]').value = desc;
    tr.querySelector('[name="u[]"]').value = un;
    tr.querySelector('[name="q[]"]').value = q;
    tr.querySelector('[name="v[]"]').value = v;
    corpo.appendChild(tr);
    recalcular();
    return tr;
  }
  document.querySelector('#add-item').addEventListener('click', () => addLinha().querySelector('input').focus());
  document.querySelectorAll('[data-sugestao]').forEach(b => b.addEventListener('click', () => addLinha(b.dataset.sugestao, b.dataset.un || 'un', '1', '')));
  corpo.addEventListener('input', recalcular);
  document.querySelector('[name=desconto]').addEventListener('input', recalcular);
  corpo.addEventListener('click', e => {
    const b = e.target.closest('.rm');
    if (b) { b.closest('tr').remove(); recalcular(); }
  });
  if (!corpo.children.length) addLinha();
  recalcular();
})();
