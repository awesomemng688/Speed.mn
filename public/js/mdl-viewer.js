import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

export function eulerQuat(a) {
    const [rx, ry, rz] = [a[0] * 0.5, a[1] * 0.5, a[2] * 0.5];
    const sr = Math.sin(rx), cr = Math.cos(rx);
    const sp = Math.sin(ry), cp = Math.cos(ry);
    const sy = Math.sin(rz), cy = Math.cos(rz);
    return new THREE.Quaternion(
        sr * cp * cy - cr * sp * sy,
        cr * sp * cy + sr * cp * sy,
        cr * cp * sy - sr * sp * cy,
        cr * cp * cy + sr * sp * sy,
    );
}

function readHeader(buffer) {
    const dv = new DataView(buffer);
    const u8 = new Uint8Array(buffer);
    const id = String.fromCharCode(u8[0], u8[1], u8[2], u8[3]);
    if (id !== 'IDST' || dv.getInt32(4, true) !== 10) {
        throw new Error('Дэмжигдээгүй model формат (GoldSrc IDST v10 биш).');
    }
    return { dv, u8 };
}

function readTextures(buffer) {
    const { dv, u8 } = readHeader(buffer);
    const numTex = dv.getInt32(180, true), texIndex = dv.getInt32(184, true);
    const textures = [];
    for (let i = 0; i < numTex; i++) {
        const o = texIndex + i * 80;
        const flags = dv.getInt32(o + 64, true), w = dv.getInt32(o + 68, true);
        const h = dv.getInt32(o + 72, true), idx = dv.getInt32(o + 76, true);
        const pal = idx + w * h;
        const data = new Uint8Array(w * h * 4);
        for (let p = 0; p < w * h; p++) {
            const c = u8[idx + p];
            data[p * 4] = u8[pal + c * 3];
            data[p * 4 + 1] = u8[pal + c * 3 + 1];
            data[p * 4 + 2] = u8[pal + c * 3 + 2];
            data[p * 4 + 3] = (flags & 0x40) && c === 255 ? 0 : 255;
        }
        const tex = new THREE.DataTexture(data, w, h, THREE.RGBAFormat);
        tex.wrapS = tex.wrapT = THREE.RepeatWrapping;
        tex.colorSpace = THREE.SRGBColorSpace;
        tex.magFilter = THREE.NearestFilter;
        tex.needsUpdate = true;
        textures.push({ tex, w, h, masked: !!(flags & 0x40) });
    }
    return textures;
}

export function parseMdl(buffer, textureBuffer = null) {
    const { dv, u8 } = readHeader(buffer);
    const i32 = (o) => dv.getInt32(o, true);
    const i16 = (o) => dv.getInt16(o, true);
    const f32 = (o) => dv.getFloat32(o, true);

    const numBones = i32(140), boneIndex = i32(144);
    const numSeq = i32(164), seqIndex = i32(168);
    const numTex = i32(180);
    const numSkinRef = i32(192), skinIndex = i32(200);
    const numBodyparts = i32(204), bodypartIndex = i32(208);

    const bones = [];
    for (let i = 0; i < numBones; i++) {
        const o = boneIndex + i * 112;
        const value = [], scale = [];
        for (let j = 0; j < 6; j++) {
            value.push(f32(o + 64 + j * 4));
            scale.push(f32(o + 88 + j * 4));
        }
        bones.push({ parent: i32(o + 32), value, scale });
    }

    const seqs = [];
    for (let i = 0; i < numSeq; i++) {
        const o = seqIndex + i * 176;
        let label = '';
        for (let k = 0; k < 32 && u8[o + k]; k++) label += String.fromCharCode(u8[o + k]);
        const external = i32(o + 156) !== 0;
        seqs.push({
            label,
            fps: f32(o + 32) || 30,
            frames: external ? 1 : Math.max(1, i32(o + 56)),
            animIndex: external ? 0 : i32(o + 124),
        });
    }

    // Frame value from GoldSrc run-length encoded animation data.
    const animValue = (offset, frame) => {
        let o = offset, k = frame;
        for (let guard = 0; guard < 4096; guard++) {
            const valid = u8[o], total = u8[o + 1];
            if (total === 0) return 0;
            if (total > k) return valid > k ? i16(o + 2 + k * 2) : i16(o + 2 + (valid - 1) * 2);
            k -= total;
            o += (valid + 1) * 2;
        }
        return 0;
    };

    const world = bones.map(() => new THREE.Matrix4());
    const tmpPos = new THREE.Vector3(), one = new THREE.Vector3(1, 1, 1);
    const pose = (seqNo, frame) => {
        const seq = seqs[seqNo];
        for (let i = 0; i < numBones; i++) {
            const v = bones[i].value.slice();
            if (seq && seq.animIndex) {
                const ao = seq.animIndex + i * 12;
                for (let j = 0; j < 6; j++) {
                    const off = dv.getUint16(ao + j * 2, true);
                    if (off) v[j] = bones[i].value[j] + animValue(ao + off, frame) * bones[i].scale[j];
                }
            }
            const m = new THREE.Matrix4().compose(tmpPos.set(v[0], v[1], v[2]), eulerQuat([v[3], v[4], v[5]]), one);
            const parent = bones[i].parent;
            world[i] = parent >= 0 && world[parent] ? world[parent].clone().multiply(m) : m;
        }
        for (const mesh of meshes) {
            const { local, boneOf, geo } = mesh;
            const out = geo.attributes.position.array;
            for (let v = 0; v < boneOf.length; v++) {
                const e = (world[boneOf[v]] || world[0]).elements;
                const x = local[v * 3], y = local[v * 3 + 1], z = local[v * 3 + 2];
                out[v * 3] = x * e[0] + y * e[4] + z * e[8] + e[12];
                out[v * 3 + 1] = x * e[1] + y * e[5] + z * e[9] + e[13];
                out[v * 3 + 2] = x * e[2] + y * e[6] + z * e[10] + e[14];
            }
            geo.attributes.position.needsUpdate = true;
        }
    };

    let textures = numTex > 0 ? readTextures(buffer) : [];
    let externalTextures = false;
    if (!textures.length && textureBuffer) {
        textures = readTextures(textureBuffer);
        externalTextures = true;
    }

    const skinRef = [];
    for (let i = 0; i < numSkinRef; i++) skinRef.push(i16(skinIndex + i * 2));

    const group = new THREE.Group();
    const meshes = [];
    let triangles = 0;

    for (let b = 0; b < numBodyparts; b++) {
        const bp = bodypartIndex + b * 76;
        if (i32(bp + 64) < 1) continue;
        const mo = i32(bp + 72);
        const numMesh = i32(mo + 72), meshIndex = i32(mo + 76);
        const vertInfo = i32(mo + 84), vertIdx = i32(mo + 88);

        for (let m = 0; m < numMesh; m++) {
            const meshOff = meshIndex + m * 20;
            const texture = textures[skinRef[i32(meshOff + 8)]] || null;
            const sw = texture ? texture.w : 1, sh = texture ? texture.h : 1;
            const local = [], boneOf = [], uv = [];

            const vertex = (v, s, t) => {
                const vo = vertIdx + v * 12;
                local.push(f32(vo), f32(vo + 4), f32(vo + 8));
                boneOf.push(u8[vertInfo + v]);
                uv.push(s / sw, t / sh);
            };

            let o = i32(meshOff + 4);
            for (;;) {
                let n = i16(o);
                o += 2;
                if (n === 0) break;
                const fan = n < 0;
                n = Math.abs(n);
                const vs = [];
                for (let k = 0; k < n; k++, o += 8) vs.push([i16(o), i16(o + 4), i16(o + 6)]);
                for (let k = 0; k < n - 2; k++) {
                    const tri = fan
                        ? [vs[0], vs[k + 1], vs[k + 2]]
                        : (k % 2 ? [vs[k + 1], vs[k], vs[k + 2]] : [vs[k], vs[k + 1], vs[k + 2]]);
                    tri.forEach((t) => vertex(t[0], t[1], t[2]));
                    triangles++;
                }
            }

            if (!boneOf.length) continue;
            const geo = new THREE.BufferGeometry();
            geo.setAttribute('position', new THREE.Float32BufferAttribute(new Float32Array(boneOf.length * 3), 3));
            geo.setAttribute('uv', new THREE.Float32BufferAttribute(uv, 2));
            const mat = new THREE.MeshBasicMaterial({
                map: texture ? texture.tex : null,
                color: texture ? 0xffffff : 0x8899aa,
                side: THREE.DoubleSide,
                transparent: !!(texture && texture.masked),
                alphaTest: texture && texture.masked ? 0.5 : 0,
            });
            group.add(new THREE.Mesh(geo, mat));
            meshes.push({ local: Float32Array.from(local), boneOf: Uint8Array.from(boneOf), geo });
        }
    }

    const info = {
        bones: numBones,
        sequences: numSeq,
        textures: textures.length,
        externalTextures,
        meshes: meshes.length,
        triangles,
        texList: textures.map((t) => t.w + '×' + t.h),
    };

    return { group, info, seqs, pose };
}

function boot() {
    const stage = document.getElementById('mdl-stage');
    if (!stage) return;
    const $ = (id) => document.getElementById(id);
    const statusEl = $('mdl-status'), infoEl = $('mdl-info'), seqEl = $('mdl-seq');
    const favKey = 'speedmn.mdl.favs';
    const favs = new Set(JSON.parse(localStorage.getItem(favKey) || '[]'));
    const saveFavs = () => localStorage.setItem(favKey, JSON.stringify([...favs]));

    const renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    stage.appendChild(renderer.domElement);
    const scene = new THREE.Scene();
    scene.background = new THREE.Color($('mdl-bg').value);
    scene.add(new THREE.GridHelper(200, 20, 0x2a3550, 0x1a2234));
    const camera = new THREE.PerspectiveCamera(45, 1, 0.1, 5000);
    camera.position.set(60, 40, 80);
    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.autoRotate = $('mdl-rotate').checked;
    controls.autoRotateSpeed = 2;

    let model = null, root = null, seqNo = 0, playing = true, clock = 0, currentBtn = null, requestId = 0;

    const resize = () => {
        renderer.setSize(stage.clientWidth, stage.clientHeight);
        camera.aspect = stage.clientWidth / stage.clientHeight;
        camera.updateProjectionMatrix();
    };
    window.addEventListener('resize', resize);
    resize();

    renderer.setAnimationLoop(() => {
        const dt = Math.min(0.1, 0.016);
        if (model && playing) {
            clock += dt;
            const seq = model.seqs[seqNo];
            if (seq && seq.frames > 1) model.pose(seqNo, Math.floor(clock * seq.fps) % seq.frames);
        }
        controls.update();
        renderer.render(scene, camera);
    });

    const dispose = (obj) => obj.traverse((o) => {
        if (o.geometry) o.geometry.dispose();
        if (o.material) {
            o.material.map?.dispose();
            o.material.dispose();
        }
    });

    const applyWire = () => root?.traverse((o) => { if (o.material) o.material.wireframe = $('mdl-wire').checked; });

    const frameCamera = () => {
        root.position.set(0, 0, 0);
        root.updateMatrixWorld(true);
        const box = new THREE.Box3().setFromObject(root);
        const size = box.getSize(new THREE.Vector3());
        const center = box.getCenter(new THREE.Vector3());
        root.position.sub(center);
        root.position.y += size.y / 2;
        const dist = Math.max(size.x, size.y, size.z, 1) * 1.8;
        camera.position.set(dist, dist * 0.6, dist);
        controls.target.set(0, size.y / 2, 0);
        controls.update();
        return size;
    };

    const fetchBuffer = async (url) => {
        const res = await fetch(url);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.arrayBuffer();
    };

    const load = async (btn) => {
        const mine = ++requestId;
        const url = btn.dataset.mdlUrl, label = btn.dataset.mdlLabel;
        statusEl.textContent = 'Ачаалж байна: ' + label + ' ...';
        try {
            const buf = await fetchBuffer(url);
            let texBuf = null;
            if (new DataView(buf).getInt32(180, true) === 0) {
                texBuf = await fetchBuffer(url.replace(/\.mdl$/i, 'T.mdl')).catch(() => null);
            }
            if (mine !== requestId) return;
            const parsed = parseMdl(buf, texBuf);
            if (!parsed.group.children.length) throw new Error('Харуулах геометр олдсонгүй.');

            if (root) { scene.remove(root); dispose(root); }
            model = parsed;
            root = new THREE.Group();
            root.add(parsed.group);
            root.rotation.x = -Math.PI / 2; // GoldSrc is Z-up
            scene.add(root);

            seqEl.innerHTML = '';
            parsed.seqs.forEach((s, i) => {
                const opt = document.createElement('option');
                opt.value = i;
                opt.textContent = s.label + (s.frames > 1 ? ' (' + s.frames + ')' : '');
                seqEl.appendChild(opt);
            });
            const idle = parsed.seqs.findIndex((s) => /idle/i.test(s.label));
            seqNo = idle >= 0 ? idle : 0;
            seqEl.value = seqNo;
            clock = 0;
            parsed.pose(seqNo, 0);
            applyWire();
            const size = frameCamera();

            const i = parsed.info;
            infoEl.innerHTML = '';
            [
                ['Файл', label + ' · ' + btn.dataset.mdlKb + 'KB'],
                ['Polygon (гурвалжин)', i.triangles.toLocaleString()],
                ['Bone', i.bones],
                ['Texture', i.textures + (i.textures ? ' (' + i.texList.join(', ') + ')' : '') + (i.externalTextures ? ' · T.mdl' : '')],
                ['Mesh', i.meshes],
                ['Sequence', i.sequences],
                ['Хэмжээ', [size.x, size.z, size.y].map((n) => n.toFixed(1)).join(' × ')],
            ].forEach(([k, v]) => {
                const row = document.createElement('div');
                const b = document.createElement('strong');
                b.textContent = k + ': ';
                row.append(b, String(v));
                infoEl.appendChild(row);
            });

            currentBtn = btn;
            $('mdl-fav').textContent = favs.has(btn.dataset.mdlId) ? '★ Дуртайгаас хасах' : '☆ Дуртайд нэмэх';
            $('mdl-fav').disabled = false;
            $('mdl-download').href = url;
            $('mdl-download').hidden = false;
            statusEl.textContent = label;
            history.replaceState(null, '', '?m=' + encodeURIComponent(btn.dataset.mdlId));
        } catch (e) {
            if (mine === requestId) statusEl.textContent = 'Алдаа: ' + e.message;
        }
    };

    const buttons = [...document.querySelectorAll('[data-mdl-url]')];
    buttons.forEach((btn) => btn.addEventListener('click', () => {
        document.querySelectorAll('[data-mdl-url].is-active').forEach((b) => b.classList.remove('is-active'));
        btn.classList.add('is-active');
        load(btn);
    }));

    let category = 'all';
    const applyFilter = () => {
        const q = $('mdl-search').value.trim().toLowerCase();
        document.querySelectorAll('[data-mdl-group]').forEach((g) => {
            let any = false;
            g.querySelectorAll('[data-mdl-url]').forEach((b) => {
                const catOk = category === 'all' || (category === 'fav' ? favs.has(b.dataset.mdlId) : b.dataset.mdlCat === category);
                const show = catOk && (!q || b.dataset.mdlLabel.toLowerCase().includes(q));
                b.hidden = !show;
                any = any || show;
            });
            g.hidden = !any;
            if ((q || category !== 'all') && any) g.open = true;
        });
    };
    $('mdl-search').addEventListener('input', applyFilter);
    document.querySelectorAll('[data-mdl-filter]').forEach((chip) => chip.addEventListener('click', () => {
        document.querySelectorAll('[data-mdl-filter].is-active').forEach((c) => c.classList.remove('is-active'));
        chip.classList.add('is-active');
        category = chip.dataset.mdlFilter;
        applyFilter();
    }));

    const markFavs = () => buttons.forEach((b) => b.classList.toggle('is-fav', favs.has(b.dataset.mdlId)));
    markFavs();
    $('mdl-fav').addEventListener('click', () => {
        if (!currentBtn) return;
        const id = currentBtn.dataset.mdlId;
        if (!favs.delete(id)) favs.add(id);
        saveFavs();
        markFavs();
        $('mdl-fav').textContent = favs.has(id) ? '★ Дуртайгаас хасах' : '☆ Дуртайд нэмэх';
        if (category === 'fav') applyFilter();
    });

    $('mdl-rotate').addEventListener('change', (e) => { controls.autoRotate = e.target.checked; });
    $('mdl-wire').addEventListener('change', applyWire);
    $('mdl-bg').addEventListener('input', (e) => { scene.background = new THREE.Color(e.target.value); });
    $('mdl-play').addEventListener('click', (e) => {
        playing = !playing;
        e.target.textContent = playing ? '❚❚ Зогсоох' : '▶ Тоглуулах';
    });
    seqEl.addEventListener('change', () => {
        seqNo = Number(seqEl.value);
        clock = 0;
        model?.pose(seqNo, 0);
    });

    const wanted = new URLSearchParams(location.search).get('m');
    const first = wanted && buttons.find((b) => b.dataset.mdlId === wanted);
    if (first) {
        first.closest('details').open = true;
        first.click();
        first.scrollIntoView({ block: 'center' });
    }
}

if (typeof document !== 'undefined') boot();
