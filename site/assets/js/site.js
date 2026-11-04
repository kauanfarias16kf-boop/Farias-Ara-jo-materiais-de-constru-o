/* Farias Araújo · site público */
(() => {
  'use strict';
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const reduz = matchMedia('(prefers-reduced-motion: reduce)');
  const ano = $('#ano'); if (ano) ano.textContent = new Date().getFullYear();

  /* ---------- cabeçalho, menu do celular, voltar ao topo ---------- */
  const topo = $('.topo');
  const subir = $('.subir');
  let rolou = null;
  const aoRolar = () => {
    const r = scrollY > 20;
    if (r !== rolou) { rolou = r; topo.classList.toggle('rolou', r); }
    subir.classList.toggle('mostra', scrollY > innerHeight * .8);
  };
  addEventListener('scroll', aoRolar, { passive: true });
  aoRolar();
  subir.addEventListener('click', () => scrollTo({ top: 0, behavior: reduz.matches ? 'auto' : 'smooth' }));

  const menuBtn = $('.menu-btn'), lista = $('#menu-lista');
  const fechaMenu = () => { lista.classList.remove('aberto'); menuBtn.setAttribute('aria-expanded', 'false'); };
  menuBtn.addEventListener('click', () => {
    const ab = !lista.classList.contains('aberto');
    lista.classList.toggle('aberto', ab);
    menuBtn.setAttribute('aria-expanded', String(ab));
  });
  $$('a', lista).forEach(a => a.addEventListener('click', fechaMenu));
  document.addEventListener('keydown', e => { if (e.key === 'Escape') fechaMenu(); });

  // o menu marca a seção que está na tela
  const links = new Map($$('a', lista).map(a => [a.getAttribute('href').slice(1), a]));
  const obsMenu = new IntersectionObserver(es => es.forEach(e => {
    if (!e.isIntersecting || !links.has(e.target.id)) return;
    links.forEach(a => a.classList.remove('ativo'));
    links.get(e.target.id).classList.add('ativo');
  }), { rootMargin: '-45% 0px -50% 0px' });
  links.forEach((_, id) => { const el = document.getElementById(id); if (el) obsMenu.observe(el); });

  /* ---------- entradas suaves ---------- */
  const io = new IntersectionObserver(es => es.forEach(e => {
    if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
  }), { rootMargin: '0px 0px -8% 0px' });
  $$('.r').forEach(el => io.observe(el));

  /* ---------- slides do topo ---------- */
  const slides = $$('.slide'), pontos = $$('.pontos button');
  let atual = 0, timer = null;
  function vai(i) {
    atual = (i + slides.length) % slides.length;
    slides.forEach((s, n) => {
      const on = n === atual;
      s.classList.toggle('ativo', on);
      s.setAttribute('aria-hidden', String(!on));
      s.toggleAttribute('inert', !on);
    });
    pontos.forEach((p, n) => p.setAttribute('aria-current', String(n === atual)));
  }
  const auto = () => { clearInterval(timer); if (!reduz.matches) timer = setInterval(() => vai(atual + 1), 7000); };
  $('.seta-s.ant').addEventListener('click', () => { vai(atual - 1); auto(); });
  $('.seta-s.prox').addEventListener('click', () => { vai(atual + 1); auto(); });
  pontos.forEach((p, n) => p.addEventListener('click', () => { vai(n); auto(); }));
  const sec = $('.slides');
  sec.addEventListener('mouseenter', () => clearInterval(timer));
  sec.addEventListener('mouseleave', auto);
  sec.addEventListener('focusin', () => clearInterval(timer));
  let x0 = null;
  sec.addEventListener('touchstart', e => (x0 = e.touches[0].clientX), { passive: true });
  sec.addEventListener('touchend', e => {
    if (x0 === null) return;
    const dx = e.changedTouches[0].clientX - x0;
    if (Math.abs(dx) > 50) { vai(atual + (dx < 0 ? 1 : -1)); auto(); }
    x0 = null;
  });
  document.addEventListener('visibilitychange', () => (document.hidden ? clearInterval(timer) : auto()));
  vai(0);
  auto();

  /* ---------- carrossel de serviços ---------- */
  const trilho = $('#trilho');
  if (trilho) {
    const ant = $('.seta-c.ant'), prox = $('.seta-c.prox');
    const passo = () => (trilho.querySelector('.card')?.getBoundingClientRect().width || 300) + 18;
    ant.addEventListener('click', () => trilho.scrollBy({ left: -passo(), behavior: reduz.matches ? 'auto' : 'smooth' }));
    prox.addEventListener('click', () => trilho.scrollBy({ left: passo(), behavior: reduz.matches ? 'auto' : 'smooth' }));
    const estado = () => {
      ant.disabled = trilho.scrollLeft < 8;
      prox.disabled = trilho.scrollLeft + trilho.clientWidth > trilho.scrollWidth - 8;
    };
    trilho.addEventListener('scroll', estado, { passive: true });
    addEventListener('resize', estado);
    estado();
  }

  /* ---------- galeria (lida do painel) ---------- */
  const galeria = $('#galeria'), vazia = $('#galeria-vazia');
  const dlg = $('#ver');
  let galeriaPedida = false;
  function abreFoto(src, alt) { $('#ver-img').src = src; $('#ver-img').alt = alt; $('#ver-txt').textContent = alt; dlg.showModal(); }
  $$('#galeria button[data-full]').forEach(b => b.addEventListener('click', () => abreFoto(b.dataset.full, b.dataset.alt)));
  async function carregaGaleria() {
    if (galeriaPedida) return; galeriaPedida = true;
    try {
      const r = await fetch('api/galeria.php', { headers: { Accept: 'application/json' } });
      if (!r.ok) return;
      const { fotos } = await r.json();
      if (!fotos || !fotos.length) return;
      const primeiraFixa = galeria.firstChild;
      fotos.forEach(f => {
        const b = document.createElement('button');
        b.type = 'button';
        b.innerHTML = '<figure><img loading="lazy" alt=""><figcaption><span></span><br></figcaption></figure>';
        const img = b.querySelector('img'); img.src = f.miniatura; img.alt = f.titulo + (f.cidade ? ', ' + f.cidade : '');
        const cap = b.querySelector('figcaption'); cap.querySelector('span').textContent = f.servico || 'Obra';
        cap.appendChild(document.createTextNode(f.titulo + (f.cidade ? ' · ' + f.cidade : '')));
        b.setAttribute('aria-label', 'Ampliar foto: ' + img.alt);
        b.addEventListener('click', () => abreFoto(f.arquivo, img.alt));
        galeria.insertBefore(b, primeiraFixa);
      });
      galeria.hidden = false; vazia.hidden = true;
    } catch { /* sem servidor: fica o convite do Instagram */ }
  }
  new IntersectionObserver((es, o) => { if (es[0].isIntersecting) { carregaGaleria(); o.disconnect(); } }, { rootMargin: '600px 0px' }).observe($('#obras'));
  $('#ver-fechar').addEventListener('click', () => dlg.close());
  dlg.addEventListener('click', e => { if (e.target === dlg) dlg.close(); });

  /* ---------- projeto em 3D (carrega só quando chega perto) ---------- */
  const p3d = $('.p3d');
  if (p3d) {
    const poster = $('.p3d-poster', p3d);
    const reserva = () => {
      // sem 3D no aparelho: as abas trocam a ilustração pronta
      $$('[data-obra]', p3d).forEach(a => a.addEventListener('click', () => {
        $$('[data-obra]', p3d).forEach(b => b.setAttribute('aria-pressed', String(b === a)));
        $$('[data-txt-obra]', p3d).forEach(t => (t.hidden = t.dataset.txtObra !== a.dataset.obra));
        poster.src = 'assets/img/3d/3d-' + a.dataset.obra + '-p.webp';
        poster.alt = { casa: 'Ilustração 3D de uma casa pré moldada pronta', predio: 'Ilustração 3D de um prédio em estrutura pré moldada', galpao: 'Ilustração 3D de um galpão pré moldado pronto' }[a.dataset.obra];
      }));
    };
    new IntersectionObserver((es, o) => {
      if (!es[0].isIntersecting) return;
      o.disconnect();
      import('./projeto3d.js?v=7').then(m => { if (!m.iniciaProjeto3D(p3d)) reserva(); }).catch(reserva);
    }, { rootMargin: '400px 0px' }).observe(p3d);
  }

  /* ---------- formulário de orçamento ---------- */
  const form = $('#form-orc');
  const secOrc = $('#contato');
  const ZAP = '5561994408678';
  const digitos = s => (s || '').replace(/\D+/g, '');
  const regras = {
    nome: v => v.trim().length >= 2 ? '' : 'Escreva seu nome.',
    whatsapp: v => { const d = digitos(v); return d.length >= 10 && d.length <= 13 ? '' : 'Confira o número com DDD, por exemplo 61 9 0000-0000.'; }
  };
  function marca(campo, erro) {
    const box = campo.closest('.campo');
    box.classList.toggle('erro', !!erro);
    box.querySelector('.msg').textContent = erro;
    campo.setAttribute('aria-invalid', erro ? 'true' : 'false');
  }
  Object.keys(regras).forEach(n => {
    const c = form.elements[n];
    c.addEventListener('blur', () => { if (c.value) marca(c, regras[n](c.value)); });
    c.addEventListener('input', () => { if (c.closest('.campo').classList.contains('erro') && !regras[n](c.value)) marca(c, ''); });
  });
  function textoZap(fd) {
    const partes = [`Olá! Meu nome é ${fd.get('nome')}.`, `Quero um orçamento de ${String(fd.get('servico')).toLowerCase()}${fd.get('cidade') ? ' em ' + fd.get('cidade') : ''}.`];
    if (fd.get('metragem')) partes.push(`Tamanho: ${fd.get('metragem')} m².`);
    if (fd.get('prazo')) partes.push(`Quero começar: ${String(fd.get('prazo')).toLowerCase()}.`);
    if (fd.get('mensagem')) partes.push(String(fd.get('mensagem')));
    return partes.join(' ');
  }
  form.addEventListener('submit', async e => {
    e.preventDefault();
    let primeiroErro = null;
    Object.keys(regras).forEach(n => { const c = form.elements[n]; const er = regras[n](c.value); marca(c, er); if (er && !primeiroErro) primeiroErro = c; });
    if (primeiroErro) { primeiroErro.focus(); return; }
    const fd = new FormData(form);
    const link = `https://wa.me/${ZAP}?text=${encodeURIComponent(textoZap(fd))}`;
    const btn = $('#f-enviar'); btn.disabled = true; btn.textContent = 'Enviando...';
    $('#f-falha').hidden = true;
    try {
      const r = await fetch(form.action, { method: 'POST', body: fd, headers: { Accept: 'application/json' } });
      const j = await r.json().catch(() => ({}));
      if (r.ok && j.ok) {
        const nome = String(fd.get('nome')).trim().split(/\s+/)[0];
        $('#s-titulo').textContent = `Recebido, ${nome}.`;
        $('#s-zap').href = link;
        secOrc.classList.add('enviado');
        $('.sucesso', secOrc).setAttribute('tabindex', '-1'); $('.sucesso', secOrc).focus();
        return;
      }
      if (r.status === 422 && j.erros) {
        Object.entries(j.erros).forEach(([n, m]) => form.elements[n] && marca(form.elements[n], m));
        throw 'campos';
      }
      throw new Error(j.erro || 'falhou');
    } catch (err) {
      if (err !== 'campos') { $('#f-falha-zap').href = link; $('#f-falha').hidden = false; }
    } finally { btn.disabled = false; btn.textContent = 'Pedir meu orçamento'; }
  });
})();
