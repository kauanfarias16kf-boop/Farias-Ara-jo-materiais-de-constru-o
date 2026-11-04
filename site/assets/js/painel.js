// Pequenos ajudantes do painel.
(() => {
  // modo claro / escuro, lembrado neste aparelho
  document.querySelectorAll('[data-tema-botao]').forEach(b => b.addEventListener('click', () => {
    const r = document.documentElement;
    const novo = r.dataset.tema === 'claro' ? 'escuro' : 'claro';
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
