/* Farias Araújo · projeto em 3D, estilo maquete branca de arquitetura */
import * as THREE from './vendor/three.module.min.js';
import { OrbitControls } from './vendor/OrbitControls.js';
import { EffectComposer } from './vendor/jsm/postprocessing/EffectComposer.js';
import { RenderPass } from './vendor/jsm/postprocessing/RenderPass.js';
import { GTAOPass } from './vendor/jsm/postprocessing/GTAOPass.js';
import { OutputPass } from './vendor/jsm/postprocessing/OutputPass.js';

const reduz = matchMedia('(prefers-reduced-motion: reduce)').matches;

export function iniciaProjeto3D(caixa, opc = {}) {
  const palco = caixa.querySelector('.p3d-palco');
  let renderer;
  try {
    renderer = new THREE.WebGLRenderer({ antialias: true, powerPreference: opc.estatico ? 'high-performance' : 'low-power', preserveDrawingBuffer: !!opc.estatico });
  } catch { return false; }
  const W = () => palco.clientWidth, H = () => palco.clientHeight;
  const dpr = opc.dpr || Math.min(devicePixelRatio || 1, 2);
  renderer.setPixelRatio(dpr);
  renderer.setSize(W(), H());
  renderer.shadowMap.enabled = true; renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  renderer.toneMapping = THREE.ACESFilmicToneMapping; renderer.toneMappingExposure = 1.05;
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  const tela = renderer.domElement;
  tela.setAttribute('aria-hidden', 'true');
  palco.appendChild(tela);

  /* ---------- materiais: só branco e cinza ---------- */
  function riscas(escuro, vertical) {
    const c = document.createElement('canvas'); c.width = c.height = 64; const g = c.getContext('2d');
    g.fillStyle = '#fff'; g.fillRect(0, 0, 64, 64);
    g.fillStyle = escuro; vertical ? g.fillRect(0, 0, 3, 64) : g.fillRect(0, 0, 64, 3);
    const t = new THREE.CanvasTexture(c); t.wrapS = t.wrapT = THREE.RepeatWrapping; t.colorSpace = THREE.SRGBColorSpace; t.anisotropy = 8; return t;
  }
  const M = {
    placa: new THREE.MeshStandardMaterial({ color: 0xf3f2ee, roughness: .92 }),
    estrutura: new THREE.MeshStandardMaterial({ color: 0xdcdad4, roughness: .9 }),
    base: new THREE.MeshStandardMaterial({ color: 0xd3d1cb, roughness: .95 }),
    calcada: new THREE.MeshStandardMaterial({ color: 0xe5e3de, roughness: .95 }),
    vidro: new THREE.MeshStandardMaterial({ color: 0x9aa1a7, roughness: .12, metalness: .35 }),
    esquadria: new THREE.MeshStandardMaterial({ color: 0x5c5f63, roughness: .5, metalness: .2 }),
    porta: new THREE.MeshStandardMaterial({ color: 0xc7c4bd, roughness: .8 }),
    telha: new THREE.MeshStandardMaterial({ color: 0xecebe7, roughness: .85, map: riscas('#b5b3ad', false), side: THREE.DoubleSide }),
    chapa: new THREE.MeshStandardMaterial({ color: 0xe9e8e4, roughness: .6, metalness: .1, map: riscas('#b0aea8', true) }),
    portao: new THREE.MeshStandardMaterial({ color: 0xd6d4cf, roughness: .6, metalness: .15, map: riscas('#a5a39d', true) }),
    metal: new THREE.MeshStandardMaterial({ color: 0x8b8d91, roughness: .5, metalness: .3 }),
    arvore: new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 1, flatShading: true }),
    chao: new THREE.MeshStandardMaterial({ color: 0xf1f0ec, roughness: 1 }),
  };
  M.portao.map.repeat.set(28, 1);
  const linha = new THREE.LineBasicMaterial({ color: 0x3e3f42, transparent: true, opacity: .3 });

  /* ---------- estúdio claro ---------- */
  const FUNDO = 0xeeede9;
  const scene = new THREE.Scene();
  scene.background = new THREE.Color(FUNDO);
  scene.fog = new THREE.Fog(FUNDO, 80, 200);
  scene.add(new THREE.HemisphereLight(0xffffff, 0xd9d6cf, 1.15));
  const sol = new THREE.DirectionalLight(0xffffff, 1.9);
  const mapa = opc.estatico ? 4096 : 2048;
  sol.castShadow = true; sol.shadow.mapSize.set(mapa, mapa);
  sol.shadow.bias = -.0003; sol.shadow.normalBias = .02; sol.shadow.radius = 4;
  const sc = sol.shadow.camera; sc.far = 200;
  scene.add(sol, sol.target);
  const chao = new THREE.Mesh(new THREE.PlaneGeometry(600, 600), M.chao);
  chao.rotation.x = -Math.PI / 2; chao.receiveShadow = true; scene.add(chao);

  const cam = new THREE.PerspectiveCamera(32, W() / H(), .1, 500);

  // oclusão de ambiente: dá volume nas quinas, como numa maquete fotografada
  let composer = null;
  try {
    const alvoRT = new THREE.WebGLRenderTarget(W() * dpr, H() * dpr, { type: THREE.HalfFloatType, samples: 4 });
    composer = new EffectComposer(renderer, alvoRT);
    composer.setPixelRatio(dpr); composer.setSize(W(), H());
    composer.addPass(new RenderPass(scene, cam));
    const ao = new GTAOPass(scene, cam, W(), H());
    ao.updateGtaoMaterial({ radius: .8, distanceExponent: 1.4, thickness: 1.4, scale: 1.1, samples: 16 });
    ao.blendIntensity = .9;
    composer.addPass(ao);
    composer.addPass(new OutputPass());
  } catch { composer = null; }
  const desenha = () => (composer ? composer.render() : renderer.render(scene, cam));

  let controle = null;
  if (!opc.estatico) {
    controle = new OrbitControls(cam, tela);
    controle.enableDamping = true; controle.dampingFactor = .08;
    controle.enablePan = false;
    controle.maxPolarAngle = Math.PI / 2 - .1; controle.minPolarAngle = .3;
    controle.autoRotate = !reduz; controle.autoRotateSpeed = .5;
    // no celular: arrastar para o lado gira, para cima e para baixo rola a página
    tela.style.touchAction = 'pan-y';
  }

  /* ---------- peças ---------- */
  let grupo = null, pecas = [];
  function poe(m, etapa, contorno = true) {
    m.castShadow = m.receiveShadow = true;
    if (contorno) m.add(new THREE.LineSegments(new THREE.EdgesGeometry(m.geometry, 25), linha));
    m.userData.etapa = etapa; m.userData.y = m.position.y;
    grupo.add(m); pecas.push(m); return m;
  }
  function bloco(w, h, d, mat, x, y, z, etapa, ry = 0, contorno = true) {
    const m = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), mat);
    m.position.set(x, y, z); m.rotation.y = ry; return poe(m, etapa, contorno);
  }
  // peça deitada entre dois pontos do plano (paredes, vigas)
  function entre(x1, z1, x2, z2, h, esp, mat, y, etapa, contorno = true) {
    const l = Math.hypot(x2 - x1, z2 - z1);
    return bloco(l, h, esp, mat, (x1 + x2) / 2, y, (z1 + z2) / 2, etapa, -Math.atan2(z2 - z1, x2 - x1), contorno);
  }
  // barra no plano vertical x/y (tesouras)
  function barra(x1, y1, x2, y2, z, s, etapa) {
    const m = new THREE.Mesh(new THREE.BoxGeometry(Math.hypot(x2 - x1, y2 - y1), s, s), M.metal);
    m.position.set((x1 + x2) / 2, (y1 + y2) / 2, z); m.rotation.z = Math.atan2(y2 - y1, x2 - x1);
    return poe(m, etapa, false);
  }
  function arvore(x, z, s = 1) {
    const t = new THREE.Mesh(new THREE.CylinderGeometry(.08 * s, .12 * s, 1.6 * s, 8), M.arvore); t.position.set(x, .8 * s, z); poe(t, 0, false);
    const c = new THREE.Mesh(new THREE.IcosahedronGeometry(1.25 * s, 1), M.arvore); c.position.set(x, 2.5 * s, z); c.scale.y = 1.15; poe(c, 0, false);
  }

  /* ---------- casa: pilares, placas horizontais e telhado de quatro águas ---------- */
  function casa() {
    const B = 1.6, NX = 8, NZ = 5, hx = NX * B / 2, hz = NZ * B / 2, Y0 = .22, HP = 3, FL = .5;
    bloco(NX * B + 3, .08, NZ * B + 3, M.calcada, 0, .04, 0, 5);
    bloco(2.2, .08, 3.2, M.calcada, -.8, .04, hz + 2.8, 5);
    bloco(NX * B + .5, Y0, NZ * B + .5, M.base, 0, Y0 / 2, 0, 1);
    // [x1, z1, x2, z2, vãos]: - placa cheia, J janela, P porta, B basculante
    const paredes = [
      [-hx, hz, hx, hz, '-JJ-P-J-'],
      [-hx, -hz, hx, -hz, '-B-J--B-'],
      [-hx, -hz, -hx, hz, '-J-J-'],
      [hx, -hz, hx, hz, '--J--'],
      [-hx, -.8, 0, -.8, '-P--'],
      [0, -.8, hx, -.8, '--P-'],
      [0, -.8, 0, hz, '-P-'],
    ];
    const nos = new Map();
    paredes.forEach(([x1, z1, x2, z2, v]) => {
      const n = v.length, dx = (x2 - x1) / n, dz = (z2 - z1) / n, ux = dx / B, uz = dz / B, f = .085;
      for (let k = 0; k <= n; k++) { const x = x1 + dx * k, z = z1 + dz * k; nos.set(x.toFixed(2) + ',' + z.toFixed(2), [x, z]); }
      entre(x1, z1, x2, z2, .2, .17, M.estrutura, Y0 + HP + .1, 2); // cinta de amarração
      for (let k = 0; k < n; k++) {
        const t = v[k], ax = x1 + dx * k, az = z1 + dz * k, bx = ax + dx, bz = az + dz;
        const vazio = { J: [2, 3], P: [0, 1, 2, 3], B: [3] }[t] || [];
        for (let r = 0; r < HP / FL; r++) {
          if (!vazio.includes(r)) entre(ax + ux * f, az + uz * f, bx - ux * f, bz - uz * f, FL - .014, .07, M.placa, Y0 + r * FL + FL / 2, 3);
        }
        if (t === '-') continue;
        const cx = (ax + bx) / 2, cz = (az + bz) / 2, ry = -Math.atan2(dz, dx), lw = B - .17;
        const [y1, y2] = { J: [1, 2], P: [0, 2], B: [1.5, 2] }[t].map(y => Y0 + y);
        const hy = y2 - y1, my = (y1 + y2) / 2;
        if (t === 'P') {
          bloco(lw - .5, hy - .04, .05, M.porta, cx, my, cz, 5, ry);
          bloco(lw - .4, .06, .1, M.esquadria, cx, y2 - .03, cz, 5, ry, false);
          continue;
        }
        bloco(lw, hy, .025, M.vidro, cx, my, cz, 5, ry, false);
        bloco(lw, .05, .1, M.esquadria, cx, y1 + .025, cz, 5, ry, false);
        bloco(lw, .05, .1, M.esquadria, cx, y2 - .025, cz, 5, ry, false);
        bloco(.04, hy, .1, M.esquadria, cx, my, cz, 5, ry, false);
        bloco(lw + .14, .05, .2, M.estrutura, cx, y1 - .03, cz, 5, ry); // peitoril
      }
    });
    nos.forEach(([x, z]) => bloco(.17, HP + .2, .17, M.estrutura, x, Y0 + (HP + .2) / 2, z, 2));
    // arranques de aço deixados na base para os pilares
    nos.forEach(([x, z]) => [-.04, .04].forEach(d => bloco(.025, .55, .025, M.metal, x + d, Y0 + .27, z + d, 1, 0, false)));

    // telhado de quatro águas
    const ex = hx + .75, ez = hz + .75, ye = Y0 + HP + .2, sobe = ez * .38, yt = ye + sobe, rx = ex - ez, FT = .4;
    const pos = [], uv = [];
    const tri = (a, b, c, ua, ub, uc) => { pos.push(...a, ...b, ...c); uv.push(...ua, ...ub, ...uc); };
    const vf = Math.hypot(ez, sobe) / FT, vl = Math.hypot(ex - rx, sobe) / FT;
    tri([-ex, ye, ez], [ex, ye, ez], [rx, yt, 0], [-ex, 0], [ex, 0], [rx, vf]);
    tri([-ex, ye, ez], [rx, yt, 0], [-rx, yt, 0], [-ex, 0], [rx, vf], [-rx, vf]);
    tri([ex, ye, -ez], [-ex, ye, -ez], [-rx, yt, 0], [ex, 0], [-ex, 0], [-rx, vf]);
    tri([ex, ye, -ez], [-rx, yt, 0], [rx, yt, 0], [ex, 0], [-rx, vf], [rx, vf]);
    tri([ex, ye, ez], [ex, ye, -ez], [rx, yt, 0], [ez, 0], [-ez, 0], [0, vl]);
    tri([-ex, ye, -ez], [-ex, ye, ez], [-rx, yt, 0], [-ez, 0], [ez, 0], [0, vl]);
    const geo = new THREE.BufferGeometry();
    geo.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
    geo.setAttribute('uv', new THREE.Float32BufferAttribute(uv, 2));
    geo.computeVertexNormals();
    poe(new THREE.Mesh(geo, M.telha), 4);
    // testeira e forro do beiral
    entre(-ex, ez, ex, ez, .2, .05, M.placa, ye - .08, 4); entre(-ex, -ez, ex, -ez, .2, .05, M.placa, ye - .08, 4);
    entre(ex, -ez, ex, ez, .2, .05, M.placa, ye - .08, 4); entre(-ex, -ez, -ex, ez, .2, .05, M.placa, ye - .08, 4);
    bloco(2 * ex, .04, 2 * ez, M.placa, 0, ye - .17, 0, 4, 0, false);

    [[-13, 9, 1.1], [12.5, -8, 1], [-12, -9.5, .9], [14, 7.5, .8]].forEach(([x, z, s]) => arvore(x, z, s));
    return { cam: [15, 8.5, 19], alvo: [0, 1.9, 0], dist: [12, 45], sol: [-22, 30, 18], sombra: 22 };
  }

  /* ---------- galpão: pilares de concreto, tesouras metálicas e telha ---------- */
  function galpao() {
    const L = 20, C = 30, hx = L / 2, hz = C / 2, Y0 = .3, HP = 7.2, FL = .5;
    bloco(L + 1, Y0, C + 1, M.base, 0, Y0 / 2, 0, 1);
    bloco(16, .08, 12, M.calcada, 0, .04, hz + 6.5, 5);
    const zs = []; for (let z = -hz; z <= hz + .01; z += 5) zs.push(z);
    const frente = [-hx, -6.5, -3, 3, 6.5, hx], fundo = [-hx, -5, 0, 5, hx];
    zs.forEach(z => [-hx, hx].forEach(x => bloco(1, .35, 1.1, M.base, x, Y0 + .17, z, 1))); // cálices
    zs.forEach(z => [-hx, hx].forEach(x => bloco(.4, HP + .6, .5, M.estrutura, x, Y0 + (HP + .6) / 2, z, 2)));
    frente.slice(1, -1).forEach(x => bloco(.3, HP, .3, M.estrutura, x, Y0 + HP / 2, hz, 2));
    fundo.slice(1, -1).forEach(x => bloco(.3, HP, .3, M.estrutura, x, Y0 + HP / 2, -hz, 2));
    [-hx, hx].forEach(x => entre(x, -hz, x, hz, .6, .5, M.estrutura, Y0 + HP + .3, 2)); // viga calha
    [hz, -hz].forEach(z => entre(-hx, z, hx, z, .8, .3, M.estrutura, Y0 + HP + .2, 2));
    // laterais: placas até 4,5 m, faixa de ventilação com venezianas, placas até o topo
    [-hx, hx].forEach(x => zs.slice(0, -1).forEach(z => {
      const a = z + .3, b = z + 5 - .3;
      for (let r = 0; r < HP / FL; r++) { const y = r * FL; if (y >= 4.5 && y < 5.5) continue; entre(x, a, x, b, FL - .015, .12, M.placa, Y0 + y + FL / 2, 3); }
      for (let i = 0; i < 4; i++) entre(x, a, x, b, .05, .2, M.metal, Y0 + 4.62 + i * .25, 5, false);
    }));
    const fecha = (xs, z, portao) => xs.slice(0, -1).forEach((x, i) => {
      const a = x + .18, b = xs[i + 1] - .18, vao = portao && Math.abs((x + xs[i + 1]) / 2) < 1;
      for (let r = 0; r < (HP - .4) / FL; r++) { if (vao && r * FL < 5.5) continue; entre(a, z, b, z, FL - .015, .12, M.placa, Y0 + r * FL + FL / 2, 3); }
    });
    fecha(frente, hz, true); fecha(fundo, -hz, false);
    // empenas
    const sobe = hx * .16, ye = Y0 + HP + .6;
    [hz, -hz].forEach(z => {
      const s = new THREE.Shape(); s.moveTo(-hx - .2, 0); s.lineTo(hx + .2, 0); s.lineTo(0, sobe + .05); s.lineTo(-hx - .2, 0);
      const m = new THREE.Mesh(new THREE.ExtrudeGeometry(s, { depth: .15, bevelEnabled: false }), M.placa);
      m.position.set(0, ye - .02, z - .075); poe(m, 4);
    });
    // tesouras metálicas sobre cada par de pilares
    const topo = x => ye + sobe * (1 - Math.abs(x) / hx), NN = 10;
    zs.slice(1, -1).forEach(z => {
      barra(-hx, ye, hx, ye, z, .1, 4);
      barra(-hx, ye, 0, ye + sobe, z, .12, 4); barra(0, ye + sobe, hx, ye, z, .12, 4);
      for (let k = 1; k < NN; k++) {
        const x = -hx + k * L / NN;
        barra(x, ye, x, topo(x), z, .06, 4);
        const xn = x + (x < 0 ? L / NN : -L / NN);
        if (k !== NN / 2) barra(x, ye, xn, topo(xn), z, .05, 4);
      }
    });
    // telha metálica de duas águas e cumeeira
    const ag = Math.atan2(sobe, hx), lado = Math.hypot(hx, sobe) + .9;
    M.chapa.map.repeat.set(1, (C + 1.4) / .3);
    [-1, 1].forEach(s => {
      const t = new THREE.Mesh(new THREE.BoxGeometry(lado, .06, C + 1.4), M.chapa);
      t.position.set(s * (lado / 2) * Math.cos(ag), ye + sobe - (lado / 2) * Math.sin(ag) + .1, 0);
      t.rotation.z = -s * ag; poe(t, 4);
    });
    bloco(.6, .12, C + 1.4, M.placa, 0, ye + sobe + .16, 0, 4);
    // portão e acabamento
    bloco(5.6, 5.4, .1, M.portao, 0, Y0 + 2.7, hz + .12, 5);
    bloco(6.2, .25, .3, M.estrutura, 0, Y0 + 5.55, hz + .12, 5);
    [[-17, 10, 1.3], [16, -12, 1.2], [-16, -14, 1], [17, 14, 1.1]].forEach(([x, z, s]) => arvore(x, z, s));
    return { cam: [30, 13, 40], alvo: [0, 3.8, 1], dist: [22, 85], sol: [-30, 42, 30], sombra: 34 };
  }

  const OBRAS = { casa, galpao };
  let visivel = true, pedido = false, mexeu = false;
  let obraAtual = null, etapaAtual = 5, quedas = [];
  function montaObra(nome) {
    if (grupo) { grupo.traverse(o => o.geometry && o.geometry.dispose()); scene.remove(grupo); }
    grupo = new THREE.Group(); pecas = []; scene.add(grupo);
    obraAtual = nome;
    const o = OBRAS[nome]();
    sol.position.set(...o.sol); sc.left = sc.bottom = -o.sombra; sc.right = sc.top = o.sombra; sc.updateProjectionMatrix();
    cam.position.set(...(opc.cam || o.cam));
    const alvo = new THREE.Vector3(...(opc.alvo || o.alvo));
    // tela estreita (celular): afasta a câmera para a obra caber inteira
    if (!opc.cam) { const asp = W() / H(); if (asp < 1.5) cam.position.sub(alvo).multiplyScalar(Math.min(1.45, 1.5 / asp)).add(alvo); }
    if (controle) { controle.target.copy(alvo); controle.minDistance = o.dist[0]; controle.maxDistance = o.dist[1]; controle.update(); }
    else cam.lookAt(alvo);
    mostraEtapa(etapaAtual, false);
  }
  function mostraEtapa(n, anima = true) {
    const antes = etapaAtual; etapaAtual = n; quedas = [];
    const agora = performance.now(); let ordem = 0;
    pecas.forEach(p => {
      const ve = p.userData.etapa <= n; p.visible = ve;
      if (ve && anima && !reduz && p.userData.etapa > antes && p.userData.etapa > 0) {
        p.position.y = p.userData.y + 4;
        quedas.push({ p, t0: agora + (ordem++ % 48) * 14 + (p.userData.etapa - antes - 1) * 420 });
      } else p.position.y = p.userData.y;
    });
    pede();
  }

  /* ---------- foto parada (para as ilustrações do site) ---------- */
  if (opc.estatico) {
    etapaAtual = opc.etapa ?? 5;
    montaObra(opc.obra || 'casa');
    desenha();
    caixa.dataset.pronto = '1';
    return true;
  }

  /* ---------- desenho só quando precisa ---------- */
  function pede() { if (!pedido && visivel && controle) { pedido = true; requestAnimationFrame(quadro); } }
  function quadro(t) {
    pedido = false;
    let segue = controle.update() || controle.autoRotate;
    if (quedas.length) {
      quedas = quedas.filter(q => {
        const k = Math.min(1, Math.max(0, (t - q.t0) / 520)), e = 1 - Math.pow(1 - k, 3);
        q.p.position.y = q.p.userData.y + 4 * (1 - e);
        return k < 1;
      });
      segue = true;
    }
    desenha();
    if (segue || mexeu) pede();
  }
  const girar = caixa.querySelector('.p3d-girar');
  controle.addEventListener('change', pede);
  controle.addEventListener('start', () => { controle.autoRotate = false; mexeu = true; caixa.classList.add('mexeu'); if (girar) girar.setAttribute('aria-pressed', 'false'); pede(); });
  controle.addEventListener('end', () => { mexeu = false; pede(); });
  new IntersectionObserver(es => { visivel = es[0].isIntersecting; if (visivel) pede(); }).observe(palco);
  new ResizeObserver(() => {
    renderer.setSize(W(), H()); if (composer) composer.setSize(W(), H());
    cam.aspect = W() / H(); cam.updateProjectionMatrix(); pede();
  }).observe(palco);

  /* ---------- botões ---------- */
  const abas = [...caixa.querySelectorAll('[data-obra]')];
  const etapas = [...caixa.querySelectorAll('[data-etapa]')];
  const textos = [...caixa.querySelectorAll('[data-txt-obra]')];
  const legenda = caixa.querySelector('.p3d-legenda');
  function marcaAba(nome) {
    abas.forEach(a => a.setAttribute('aria-pressed', String(a.dataset.obra === nome)));
    textos.forEach(t => (t.hidden = t.dataset.txtObra !== nome));
  }
  function marcaEtapa(n) {
    etapas.forEach(b => b.setAttribute('aria-current', String(+b.dataset.etapa === n)));
    caixa.dataset.etapa = n;
    const b = etapas.find(b => +b.dataset.etapa === n);
    if (legenda && b) legenda.textContent = b.querySelector('span').firstChild.textContent + ': ' + b.querySelector('small:not([hidden])').textContent;
  }
  abas.forEach(a => a.addEventListener('click', () => {
    if (a.dataset.obra === obraAtual) return;
    marcaAba(a.dataset.obra); etapaAtual = 5; marcaEtapa(5); montaObra(a.dataset.obra);
  }));
  etapas.forEach(b => b.addEventListener('click', () => {
    const n = +b.dataset.etapa;
    marcaEtapa(n);
    if (n <= etapaAtual) mostraEtapa(0, false);
    mostraEtapa(n, true);
  }));
  if (girar) {
    girar.setAttribute('aria-pressed', String(controle.autoRotate));
    girar.addEventListener('click', () => { controle.autoRotate = !controle.autoRotate; girar.setAttribute('aria-pressed', String(controle.autoRotate)); pede(); });
  }

  marcaAba('casa'); marcaEtapa(5); montaObra('casa');
  caixa.classList.add('pronto3d');
  return true;
}
