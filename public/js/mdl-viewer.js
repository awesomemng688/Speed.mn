import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

const stage = document.getElementById('mdl-stage');
const statusEl = document.getElementById('mdl-status');
const renderer = new THREE.WebGLRenderer({ antialias: true });
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
stage.appendChild(renderer.domElement);

const scene = new THREE.Scene();
scene.background = new THREE.Color(0x0b0f17);
scene.add(new THREE.GridHelper(200, 20, 0x2a3550, 0x1a2234));
const camera = new THREE.PerspectiveCamera(45, 1, 0.1, 5000);
camera.position.set(60, 40, 80);
const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;
controls.autoRotate = true;
controls.autoRotateSpeed = 2;

let current = null;

function resize() {
    const w = stage.clientWidth;
    const h = stage.clientHeight;
    renderer.setSize(w, h);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
}
window.addEventListener('resize', resize);
resize();
renderer.setAnimationLoop(() => {
    controls.update();
    renderer.render(scene, camera);
});

function eulerQuat(a) {
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

function parseMdl(buffer) {
    const dv = new DataView(buffer);
    const u8 = new Uint8Array(buffer);
    const i32 = (o) => dv.getInt32(o, true);
    const i16 = (o) => dv.getInt16(o, true);
    const f32 = (o) => dv.getFloat32(o, true);

    const id = String.fromCharCode(u8[0], u8[1], u8[2], u8[3]);
    if (id !== 'IDST' || i32(4) !== 10) {
        throw new Error('Дэмжигдээгүй model формат (GoldSrc IDST v10 биш).');
    }

    const numBones = i32(140), boneIndex = i32(144);
    const numSeq = i32(164), seqIndex = i32(168);
    const numTex = i32(180), texIndex = i32(184);
    const numSkinRef = i32(192), skinIndex = i32(200);
    const numBodyparts = i32(204), bodypartIndex = i32(208);

    // Bone pose: base values plus frame 0 of the first sequence.
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

    const pose = bones.map((b) => b.value.slice());
    if (numSeq > 0 && i32(seqIndex + 156) === 0) {
        const animBase = i32(seqIndex + 124);
        for (let i = 0; i < numBones; i++) {
            const ao = animBase + i * 12;
            for (let j = 0; j < 6; j++) {
                const off = dv.getUint16(ao + j * 2, true);
                if (off) {
                    const v = i16(ao + off + 2);
                    pose[i][j] = bones[i].value[j] + v * bones[i].scale[j];
                }
            }
        }
    }

    const world = [];
    for (let i = 0; i < numBones; i++) {
        const m = new THREE.Matrix4().compose(
            new THREE.Vector3(pose[i][0], pose[i][1], pose[i][2]),
            eulerQuat([pose[i][3], pose[i][4], pose[i][5]]),
            new THREE.Vector3(1, 1, 1),
        );
        world.push(bones[i].parent >= 0 && world[bones[i].parent] ? world[bones[i].parent].clone().multiply(m) : m);
    }

    const textures = [];
    for (let i = 0; i < numTex; i++) {
        const o = texIndex + i * 80;
        const flags = i32(o + 64), w = i32(o + 68), h = i32(o + 72), idx = i32(o + 76);
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

    const skinRef = [];
    for (let i = 0; i < numSkinRef; i++) skinRef.push(i16(skinIndex + i * 2));

    const group = new THREE.Group();
    for (let b = 0; b < numBodyparts; b++) {
        const bp = bodypartIndex + b * 76;
        if (i32(bp + 64) < 1) continue;
        const mo = i32(bp + 72);
        const numMesh = i32(mo + 72), meshIndex = i32(mo + 76);
        const vertInfo = i32(mo + 84), vertIdx = i32(mo + 88);

        for (let m = 0; m < numMesh; m++) {
            const meshOff = meshIndex + m * 20;
            const triIdx = i32(meshOff + 4);
            const texture = textures[skinRef[i32(meshOff + 8)]] || null;
            const sw = texture ? texture.w : 1, sh = texture ? texture.h : 1;
            const pos = [], uv = [];

            const vertex = (v, s, t) => {
                const vo = vertIdx + v * 12;
                const p = new THREE.Vector3(f32(vo), f32(vo + 4), f32(vo + 8));
                const bone = u8[vertInfo + v];
                if (world[bone]) p.applyMatrix4(world[bone]);
                pos.push(p.x, p.y, p.z);
                uv.push(s / sw, t / sh);
            };

            let o = triIdx;
            for (;;) {
                let n = i16(o);
                o += 2;
                if (n === 0) break;
                const fan = n < 0;
                n = Math.abs(n);
                const vs = [];
                for (let k = 0; k < n; k++, o += 8) {
                    vs.push([i16(o), i16(o + 4), i16(o + 6)]);
                }
                for (let k = 0; k < n - 2; k++) {
                    const tri = fan
                        ? [vs[0], vs[k + 1], vs[k + 2]]
                        : (k % 2 ? [vs[k + 1], vs[k], vs[k + 2]] : [vs[k], vs[k + 1], vs[k + 2]]);
                    tri.forEach((t) => vertex(t[0], t[1], t[2]));
                }
            }

            if (!pos.length) continue;
            const geo = new THREE.BufferGeometry();
            geo.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
            geo.setAttribute('uv', new THREE.Float32BufferAttribute(uv, 2));
            const mat = new THREE.MeshBasicMaterial({
                map: texture ? texture.tex : null,
                color: texture ? 0xffffff : 0x8899aa,
                side: THREE.DoubleSide,
                transparent: !!(texture && texture.masked),
                alphaTest: texture && texture.masked ? 0.5 : 0,
            });
            group.add(new THREE.Mesh(geo, mat));
        }
    }

    return group;
}

async function load(url, label) {
    statusEl.textContent = 'Ачаалж байна: ' + label + ' ...';
    try {
        const res = await fetch(url);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const group = parseMdl(await res.arrayBuffer());
        if (!group.children.length) throw new Error('Харуулах геометр олдсонгүй.');

        const root = new THREE.Group();
        root.add(group);
        root.rotation.x = -Math.PI / 2; // GoldSrc is Z-up
        if (current) {
            scene.remove(current);
            current.traverse((o) => {
                if (o.geometry) o.geometry.dispose();
                if (o.material) {
                    if (o.material.map) o.material.map.dispose();
                    o.material.dispose();
                }
            });
        }
        scene.add(root);
        current = root;
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
        statusEl.textContent = label;
    } catch (e) {
        statusEl.textContent = 'Алдаа: ' + e.message;
    }
}

document.querySelectorAll('[data-mdl-url]').forEach((btn) => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('[data-mdl-url].is-active').forEach((b) => b.classList.remove('is-active'));
        btn.classList.add('is-active');
        load(btn.dataset.mdlUrl, btn.dataset.mdlLabel);
    });
});

const search = document.getElementById('mdl-search');
search?.addEventListener('input', () => {
    const q = search.value.trim().toLowerCase();
    document.querySelectorAll('[data-mdl-group]').forEach((g) => {
        let any = false;
        g.querySelectorAll('[data-mdl-url]').forEach((b) => {
            const show = !q || b.dataset.mdlLabel.toLowerCase().includes(q);
            b.hidden = !show;
            any = any || show;
        });
        g.hidden = !any;
        if (q && any) g.open = true;
    });
});
