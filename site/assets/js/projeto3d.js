/* Farias Araújo · projeto em 3D (casa e galpão, etapa por etapa) */
import * as THREE from './vendor/three.module.min.js';
import { OrbitControls } from './vendor/OrbitControls.js';

const reduz = matchMedia('(prefers-reduced-motion: reduce)').matches;

export function iniciaProjeto3D(caixa) {
  const palco = caixa.querySelector('.p3d-palco');
  let renderer;
  try {
    renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'low-power' });
  } catch { return false; }
  const W = () => palco.clientWidth, H = () => palco.clientHeight;
  renderer.setPixelRatio(Math.min(devicePixelRatio || 1, 2));
  renderer.setSize(W(), H());
  renderer.shadowMap.enabled = true; renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  renderer.toneMapping = THREE.ACESFilmicToneMapping; renderer.toneMappingExposure = .86;
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  const tela = renderer.domElement;
  tela.setAttribute('aria-hidden', 'true');
  palco.appendChild(tela);

  /* ---------- materiais com textura feita na hora ---------- */
  function ruido(cor, v, tam = 256, manchas = 0, rep = 1) {
    const c = document.createElement('canvas'); c.width = c.height = tam; const g = c.getContext('2d');
    g.fillStyle = cor; g.fillRect(0, 0, tam, tam);
    const im = g.getImageData(0, 0, tam, tam), d = im.data;
    let s = 7; const rnd = () => (s = (s * 16807) % 2147483647) / 2147483647;
    for (let i = 0; i < d.length; i += 4) { const n = (rnd() - .5) * v; d[i] += n; d[i + 1] += n; d[i + 2] += n; }
    g.putImageData(im, 0, 0);
    for (let k = 0; k < manchas; k++) { g.fillStyle = `rgba(90,85,80,${.03 + rnd() * .05})`; g.beginPath(); g.ellipse(rnd() * tam, rnd() * tam, 5 + rnd() * 30, 2 + rnd() * 15, rnd() * 3, 0, 7); g.fill(); }
    const t = new THREE.CanvasTexture(c); t.wrapS = t.wrapT = THREE.RepeatWrapping; t.colorSpace = THREE.SRGBColorSpace; t.repeat.set(rep, rep); return t;
  }
  function listras(base, linha, n, ry = 1) {
    const c = document.createElement('canvas'); c.width = c.height = 128; const g = c.getContext('2d');
    g.fillStyle = base; g.fillRect(0, 0, 128, 128);
    for (let i = 0; i < n; i++) { const x = i * 128 / n, gr = g.createLinearGradient(x, 0, x + 128 / n, 0); gr.addColorStop(0, linha); gr.addColorStop(.5, base); gr.addColorStop(1, linha); g.fillStyle = gr; g.fillRect(x, 0, 128 / n, 128); }
    const t = new THREE.CanvasTexture(c); t.wrapS = t.wrapT = THREE.RepeatWrapping; t.colorSpace = THREE.SRGBColorSpace; t.repeat.set(1, ry); return t;
  }
  const M = {
    placa: new THREE.MeshStandardMaterial({ map: ruido('#aca69c', 28, 256, 30, 2), roughness: .92 }),
    pilar: new THREE.MeshStandardMaterial({ map: ruido('#a6a198', 22), roughness: .9 }),
    laje: new THREE.MeshStandardMaterial({ map: ruido('#b3aea4', 20), roughness: .9 }),
    piso: new THREE.MeshStandardMaterial({ map: ruido('#a9a59d', 14), roughness: .7 }),
    vidro: new THREE.MeshStandardMaterial({ color: 0x2a3440, roughness: .08, metalness: .6 }),
    caixilho: new THREE.MeshStandardMaterial({ color: 0x22232a, roughness: .5, metalness: .4 }),
    ouro: new THREE.MeshStandardMaterial({ color: 0xc9a04a, roughness: .4, metalness: .6 }),
    porta: new THREE.MeshStandardMaterial({ map: ruido('#6e4f33', 30), roughness: .7 }),
    telha: new THREE.MeshStandardMaterial({ map: listras('#9aa0a6', '#6f757b', 12, 8), roughness: .45, metalness: .55 }),
    portao: new THREE.MeshStandardMaterial({ map: listras('#d9d6cf', '#a8a49c', 20), roughness: .5, metalness: .4 }),
    terra: new THREE.MeshStandardMaterial({ map: ruido('#b0744a', 34, 256, 0, 14), roughness: 1 }),
    grama: new THREE.MeshStandardMaterial({ map: ruido('#7f8f4a', 40, 256, 0, 30), roughness: 1 }),
    muro: new THREE.MeshStandardMaterial({ color: 0xd8d2c6, roughness: .95 }),
    caixaAgua: new THREE.MeshStandardMaterial({ color: 0x2b6cb3, roughness: .4 }),
  };

  [M.placa, M.pilar, M.laje, M.piso, M.muro].forEach(m => (m.envMapIntensity = .35));

  /* ---------- cena, luz e chão ---------- */
  const scene = new THREE.Scene();
  scene.fog = new THREE.Fog(0xe9e4da, 80, 220);
  const pm = new THREE.PMREMGenerator(renderer);
  const ceu = new THREE.Scene(); ceu.background = new THREE.Color(0x9bb8d8);
  scene.environment = pm.fromScene(ceu, .04).texture;
  scene.add(new THREE.HemisphereLight(0xece6dc, 0x9a7a5e, .85));
  const sol = new THREE.DirectionalLight(0xffdcae, 2.7);
  sol.castShadow = true; sol.shadow.mapSize.set(2048, 2048);
  sol.shadow.bias = -.0004; sol.shadow.normalBias = .03;
  const sc = sol.shadow.camera; sc.far = 160;
  scene.add(sol, sol.target);
  const chao = new THREE.Mesh(new THREE.PlaneGeometry(300, 300), M.grama);
  chao.rotation.x = -Math.PI / 2; chao.receiveShadow = true; scene.add(chao);

  const cam = new THREE.PerspectiveCamera(36, W() / H(), .1, 500);
  const controle = new OrbitControls(cam, tela);
  controle.enableDamping = true; controle.dampingFactor = .08;
  controle.enablePan = false;
  controle.maxPolarAngle = Math.PI / 2 - .08; controle.minPolarAngle = .25;
  controle.autoRotate = !reduz; controle.autoRotateSpeed = .6;
  // no celular: arrastar para o lado gira, arrastar para cima e para baixo continua rolando a página
  tela.style.touchAction = 'pan-y';

  /* ---------- montagem das duas obras ---------- */
  // cada peça guarda a etapa em que aparece; o botão de etapa mostra tudo até ela
  let grupo = null, pecas = [];
  function caixaM(w, h, d, mat, x, y, z, etapa, ry = 0) {
    const m = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), mat);
    m.position.set(x, y, z); m.rotation.y = ry; m.castShadow = m.receiveShadow = true;
    m.userData = { etapa, y }; grupo.add(m); pecas.push(m); return m;
  }
  function malha(geo, mat, etapa) {
    const m = new THREE.Mesh(geo, mat); m.castShadow = m.receiveShadow = true;
    m.userData = { etapa }; grupo.add(m); pecas.push(m); return m;
  }
  function plano(w, d, mat, x, z, etapa, y = .01) {
    const m = new THREE.Mesh(new THREE.PlaneGeometry(w, d), mat);
    m.rotation.x = -Math.PI / 2; m.position.set(x, y, z); m.receiveShadow = true;
    m.userData = { etapa, y }; grupo.add(m); pecas.push(m); return m;
  }
  function muro(x1, z1, x2, z2) { const l = Math.hypot(x2 - x1, z2 - z1); caixaM(l, 2, .15, M.muro, (x1 + x2) / 2, 1, (z1 + z2) / 2, 0, -Math.atan2(z2 - z1, x2 - x1)); }
  function paredeX(x0, x1, z, h, vaos, etapa) {
    const larg = 1.25, junta = .025, esp = .12;
    for (let x = x0; x < x1 - .01; x += larg) {
      const w = Math.min(larg, x1 - x) - junta, cx = x + w / 2 + junta / 2;
      const v = vaos.find(v => cx > v.a && cx < v.b);
      if (!v) { caixaM(w, h, esp, M.placa, cx, h / 2 + .25, z, etapa); continue; }
      if (v.p > 0) caixaM(w, v.p, esp, M.placa, cx, v.p / 2 + .25, z, etapa);
      caixaM(w, h - v.t, esp, M.placa, cx, v.t + (h - v.t) / 2 + .25, z, etapa);
    }
    vaos.forEach(v => {
      const w = v.b - v.a, hv = v.t - v.p, cx = (v.a + v.b) / 2, cy = .25 + v.p + hv / 2;
      if (v.porta) { caixaM(w - .1, hv - .05, .06, M.porta, cx, cy, z, 5); return; }
      caixaM(w, hv, .03, M.vidro, cx, cy, z, 5);
      caixaM(w + .08, .07, esp + .04, M.caixilho, cx, .25 + v.p, z, 5); caixaM(w + .08, .07, esp + .04, M.caixilho, cx, .25 + v.t, z, 5);
      caixaM(.06, hv, esp + .04, M.caixilho, v.a, cy, z, 5); caixaM(.06, hv, esp + .04, M.caixilho, v.b, cy, z, 5); caixaM(.05, hv, esp + .04, M.caixilho, cx, cy, z, 5);
    });
  }
  function paredeZ(z0, z1, x, h, etapa) {
    const larg = 1.25, junta = .025;
    for (let z = z0; z < z1 - .01; z += larg) { const w = Math.min(larg, z1 - z) - junta; caixaM(.12, h, w, M.placa, x, h / 2 + .25, z + w / 2 + junta / 2, etapa); }
  }

  const OBRAS = {
    casa: {
      cam: [17, 7, 19], alvo: [0, 1.6, 0], dist: [11, 42], sol: [-40, 34, 28], sombra: 26,
      monta() {
        const L = 12, P = 8, h = 2.9;
        plano(40, 30, M.terra, 0, 0, 0);
        muro(-20, -15, 20, -15); muro(-20, -15, -20, 15); muro(20, -15, 20, 15);
        caixaM(L + .6, .25, P + .6, M.piso, 0, .125, 0, 1);
        caixaM(2.2, .12, 1.4, M.piso, 1.2, .06, P / 2 + 1, 1);
        [-L / 2, -L / 4, 0, L / 4, L / 2].forEach(x => [-P / 2, 0, P / 2].forEach(z => {
          if (z === 0 && Math.abs(x) < L / 2) return;
          caixaM(.22, h + .2, .22, M.pilar, x, (h + .2) / 2 + .25, z, 2);
        }));
        caixaM(L + .2, .3, .25, M.pilar, 0, h + .35, P / 2, 2); caixaM(L + .2, .3, .25, M.pilar, 0, h + .35, -P / 2, 2);
        caixaM(.25, .3, P, M.pilar, -L / 2, h + .35, 0, 2); caixaM(.25, .3, P, M.pilar, L / 2, h + .35, 0, 2);
        paredeX(-L / 2, L / 2, P / 2, h, [{ a: -4.6, b: -2.1, p: 1, t: 2.3 }, { a: .5, b: 1.9, p: 0, t: 2.2, porta: true }, { a: 2.6, b: 5.1, p: 1, t: 2.3 }], 3);
        paredeX(-L / 2, L / 2, -P / 2, h, [{ a: -2, b: 0, p: 1.4, t: 2.3 }], 3);
        paredeZ(-P / 2, P / 2, -L / 2, h, 3); paredeZ(-P / 2, P / 2, L / 2, h, 3);
        caixaM(L + 1.2, .22, P + 1.2, M.laje, 0, h + .6, 0, 4);
        caixaM(L + 1.2, .5, .1, M.laje, 0, h + .94, P / 2 + .55, 4); caixaM(L + 1.2, .5, .1, M.laje, 0, h + .94, -P / 2 - .55, 4);
        caixaM(.1, .5, P + 1.2, M.laje, L / 2 + .55, h + .94, 0, 4); caixaM(.1, .5, P + 1.2, M.laje, -L / 2 - .55, h + .94, 0, 4);
        caixaM(L + 1.22, .06, .12, M.ouro, 0, h + .74, P / 2 + .6, 5);
        // cavalete com caixa d'água, como nas obras de verdade
        [[-9, -9], [-7.6, -9], [-9, -7.6], [-7.6, -7.6]].forEach(([x, z]) => caixaM(.12, 3.2, .12, M.pilar, x, 1.6, z, 0));
        const cx = malha(new THREE.CylinderGeometry(.85, .7, .9, 24), M.caixaAgua, 0); cx.position.set(-8.3, 3.65, -8.3); cx.userData.y = 3.65;
      },
    },
    galpao: {
      cam: [30, 11, 42], alvo: [0, 4, 0], dist: [24, 80], sol: [-50, 40, 45], sombra: 40,
      monta() {
        const L = 20, C = 30, h = 7, cume = 3.2;
        plano(60, 60, M.terra, 0, 6, 0);
        caixaM(L + 1, .3, C + 1, M.piso, 0, .15, 0, 1);
        for (let z = -C / 2; z <= C / 2 + .01; z += 5) [-L / 2, L / 2].forEach(x => caixaM(.45, h, .45, M.pilar, x, h / 2 + .3, z, 2));
        [-L / 2, L / 2].forEach(x => caixaM(.5, .5, C, M.pilar, x, h + .1, 0, 2));
        [-L / 2, L / 2].forEach(x => {
          for (let z = -C / 2; z < C / 2 - .01; z += 1.25) caixaM(.15, 4.5, 1.22, M.placa, x, 2.55, z + .625, 3);
          caixaM(.12, 1.2, C, M.vidro, x, 5.6, 0, 5);
        });
        for (let x = -L / 2; x < L / 2 - .01; x += 1.25) {
          const cx = x + .62;
          if (Math.abs(cx) < 3) { caixaM(1.24, 1.85, .15, M.placa, cx, 6.42, C / 2, 3); continue; }
          caixaM(1.22, h, .15, M.placa, cx, h / 2 + .3, C / 2, 3);
        }
        for (let x = -L / 2; x < L / 2 - .01; x += 1.25) caixaM(1.22, h, .15, M.placa, x + .62, h / 2 + .3, -C / 2, 3);
        const tri = new THREE.Shape(); tri.moveTo(-L / 2, 0); tri.lineTo(L / 2, 0); tri.lineTo(0, cume); tri.lineTo(-L / 2, 0);
        [C / 2, -C / 2].forEach(z => { const m = malha(new THREE.ExtrudeGeometry(tri, { depth: .15, bevelEnabled: false }), M.placa, 4); m.position.set(0, h + .3, z - .075); m.userData.y = h + .3; });
        const ag = Math.atan2(cume, L / 2), lado = Math.hypot(L / 2, cume) + .9;
        [-1, 1].forEach(s => { const t = malha(new THREE.BoxGeometry(lado, .1, C + 1.6), M.telha, 4); const y = h + .3 + cume / 2 + .05; t.position.set(s * (L / 4 + .2), y, 0); t.rotation.z = -s * ag; t.userData.y = y; });
        caixaM(.3, .3, C + 1.6, M.laje, 0, h + .3 + cume + .05, 0, 4);
        caixaM(6, 5.2, .1, M.portao, 0, 2.9, C / 2 + .04, 5);
        caixaM(6.4, .22, .12, M.ouro, 0, 5.6, C / 2 + .1, 5);
        plano(14, 12, M.piso, 0, C / 2 + 6.5, 5, .02);
      },
    },
  };

  /* ---------- troca de obra e de etapa ---------- */
  let obraAtual = null, etapaAtual = 5, quedas = [];
  function montaObra(nome) {
    if (grupo) { grupo.traverse(o => o.geometry && o.geometry.dispose()); scene.remove(grupo); }
    grupo = new THREE.Group(); pecas = []; scene.add(grupo);
    const o = OBRAS[nome]; obraAtual = nome; o.monta();
    sol.position.set(...o.sol); sc.left = sc.bottom = -o.sombra; sc.right = sc.top = o.sombra; sc.updateProjectionMatrix();
    cam.position.set(...o.cam); controle.target.set(...o.alvo);
    controle.minDistance = o.dist[0]; controle.maxDistance = o.dist[1];
    controle.update();
    mostraEtapa(etapaAtual, false);
  }
  function mostraEtapa(n, anima = true) {
    const antes = etapaAtual; etapaAtual = n; quedas = [];
    const agora = performance.now();
    let ordem = 0;
    pecas.forEach(p => {
      const ve = p.userData.etapa <= n;
      p.visible = ve;
      if (ve && anima && !reduz && p.userData.etapa > antes && p.userData.etapa > 0 && p.userData.y !== undefined) {
        p.position.y = p.userData.y + 4;
        quedas.push({ p, t0: agora + (ordem++ % 40) * 18 + (p.userData.etapa - antes - 1) * 380 });
      } else if (p.userData.y !== undefined) p.position.y = p.userData.y;
    });
    pede();
  }

  /* ---------- desenho só quando precisa ---------- */
  let visivel = true, pedido = false, mexeu = false;
  function pede() { if (!pedido && visivel) { pedido = true; requestAnimationFrame(quadro); } }
  function quadro(t) {
    pedido = false;
    let segue = controle.update() || controle.autoRotate;
    if (quedas.length) {
      quedas = quedas.filter(q => {
        const k = Math.min(1, Math.max(0, (t - q.t0) / 520));
        const e = 1 - Math.pow(1 - k, 3);
        q.p.position.y = q.p.userData.y + 4 * (1 - e);
        return k < 1;
      });
      segue = true;
    }
    renderer.render(scene, cam);
    if (segue || mexeu) pede();
  }
  controle.addEventListener('change', pede);
  controle.addEventListener('start', () => { controle.autoRotate = false; mexeu = true; caixa.classList.add('mexeu'); pede(); });
  controle.addEventListener('end', () => { mexeu = false; pede(); });
  new IntersectionObserver(es => { visivel = es[0].isIntersecting; if (visivel) pede(); }).observe(palco);
  new ResizeObserver(() => { renderer.setSize(W(), H()); cam.aspect = W() / H(); cam.updateProjectionMatrix(); pede(); }).observe(palco);

  /* ---------- botões ---------- */
  const abas = [...caixa.querySelectorAll('[data-obra]')];
  const etapas = [...caixa.querySelectorAll('[data-etapa]')];
  const textos = [...caixa.querySelectorAll('[data-txt-obra]')];
  function marcaAba(nome) {
    abas.forEach(a => a.setAttribute('aria-pressed', String(a.dataset.obra === nome)));
    textos.forEach(t => (t.hidden = t.dataset.txtObra !== nome));
  }
  const legenda = caixa.querySelector('.p3d-legenda');
  function marcaEtapa(n) {
    etapas.forEach(b => b.setAttribute('aria-current', String(+b.dataset.etapa === n)));
    caixa.dataset.etapa = n;
    const b = etapas.find(b => +b.dataset.etapa === n);
    if (legenda && b) legenda.textContent = b.querySelector('span').firstChild.textContent + ': ' + b.querySelector('small:not([hidden])').textContent;
  }
  abas.forEach(a => a.addEventListener('click', () => {
    if (a.dataset.obra === obraAtual) return;
    marcaAba(a.dataset.obra); etapaAtual = 5; marcaEtapa(5); montaObra(a.dataset.obra);
    controle.autoRotate = !reduz && !caixa.classList.contains('mexeu');
  }));
  etapas.forEach(b => b.addEventListener('click', () => {
    const n = +b.dataset.etapa;
    marcaEtapa(n);
    if (n <= etapaAtual) { mostraEtapa(0, false); etapaAtual = 0; }
    mostraEtapa(n, true);
  }));
  const girar = caixa.querySelector('.p3d-girar');
  if (girar) girar.addEventListener('click', () => {
    controle.autoRotate = !controle.autoRotate;
    girar.setAttribute('aria-pressed', String(controle.autoRotate));
    pede();
  });
  if (girar) girar.setAttribute('aria-pressed', String(controle.autoRotate));

  marcaAba('casa'); marcaEtapa(5); montaObra('casa');
  caixa.classList.add('pronto3d');
  return true;
}
