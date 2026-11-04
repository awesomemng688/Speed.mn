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
    const numSkinRef = i32(192), numSkinFamilies = i32(196), skinIndex = i32(200);
    const numHitboxes = i32(156), hitboxIndex = i32(160);
    const numBodyparts = i32(204), bodypartIndex = i32(208);
    const numAttachments = i32(212), attachmentIndex = i32(216);

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
            external,
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
        for (const item of hitboxes) {
            if (world[item.bone]) item.object.matrix.copy(world[item.bone]).multiply(new THREE.Matrix4().makeTranslation(item.center.x, item.center.y, item.center.z));
        }
        for (const item of attachments) {
            if (world[item.bone]) item.object.matrix.copy(world[item.bone]).multiply(new THREE.Matrix4().makeTranslation(item.local.x, item.local.y, item.local.z));
        }
        overlays.updateMatrixWorld(true);
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
            geo.computeVertexNormals();
        }
    };

    let textures = numTex > 0 ? readTextures(buffer) : [];
    let externalTextures = false;
    if (!textures.length && textureBuffer) {
        textures = readTextures(textureBuffer);
        externalTextures = true;
    }

    const skinRefs = [];
    for (let i = 0; i < numSkinRef * numSkinFamilies; i++) skinRefs.push(i16(skinIndex + i * 2));

    const group = new THREE.Group();
    const meshes = [];
    const bodyParts = [];
    let triangles = 0;

    for (let b = 0; b < numBodyparts; b++) {
        const bp = bodypartIndex + b * 76;
        const variants = Math.max(1, i32(bp + 64)), base = Math.max(1, i32(bp + 68));
        const modelIndex = i32(bp + 72), choices = [];
        for (let variant = 0; variant < variants; variant++) {
            const mo = modelIndex + variant * 112;
            const numMesh = i32(mo + 72), meshIndex = i32(mo + 76);
            const vertInfo = i32(mo + 84), vertIdx = i32(mo + 88);
            const variantGroup = new THREE.Group();
            variantGroup.visible = variant === 0;

            for (let m = 0; m < numMesh; m++) {
                const meshOff = meshIndex + m * 20;
                const textureIndex = i32(meshOff + 8);
                const texture = textures[skinRefs[textureIndex]] || null;
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
                const mat = new THREE.MeshStandardMaterial({
                    map: texture ? texture.tex : null,
                    color: texture ? 0xffffff : 0x8899aa,
                    side: THREE.DoubleSide,
                    transparent: !!(texture && texture.masked),
                    alphaTest: texture && texture.masked ? 0.5 : 0,
                    roughness: 0.82,
                    metalness: 0.05,
                });
                const mesh = new THREE.Mesh(geo, mat);
                mesh.castShadow = true;
                mesh.receiveShadow = true;
                variantGroup.add(mesh);
                meshes.push({ local: Float32Array.from(local), boneOf: Uint8Array.from(boneOf), geo, material: mat, textureIndex });
            }
            group.add(variantGroup);
            choices.push(variantGroup);
        }
        bodyParts.push({ choices, base });
    }

    const overlays = new THREE.Group();
    overlays.visible = false;
    group.add(overlays);
    const hitboxes = [];
    for (let i = 0; i < numHitboxes; i++) {
        const o = hitboxIndex + i * 32, bone = i32(o);
        const min = new THREE.Vector3(f32(o + 8), f32(o + 12), f32(o + 16));
        const max = new THREE.Vector3(f32(o + 20), f32(o + 24), f32(o + 28));
        const size = max.clone().sub(min), center = min.clone().add(max).multiplyScalar(0.5);
        const box = new THREE.Mesh(
            new THREE.BoxGeometry(Math.max(size.x, 0.01), Math.max(size.y, 0.01), Math.max(size.z, 0.01)),
            new THREE.MeshBasicMaterial({ color: 0xff3454, wireframe: true }),
        );
        box.matrixAutoUpdate = false;
        overlays.add(box);
        hitboxes.push({ object: box, bone, center });
    }

    const attachments = [];
    for (let i = 0; i < numAttachments; i++) {
        const o = attachmentIndex + i * 56, bone = i32(o);
        const local = new THREE.Vector3(f32(o + 8), f32(o + 12), f32(o + 16));
        const marker = new THREE.Group();
        marker.matrixAutoUpdate = false;
        const dot = new THREE.Mesh(
            new THREE.SphereGeometry(1.5, 8, 6),
            new THREE.MeshBasicMaterial({ color: 0x35d7ff }),
        );
        marker.add(dot, new THREE.AxesHelper(8));
        overlays.add(marker);
        attachments.push({ object: marker, bone, local });
    }

    const applyBody = (partIndex, variant) => {
        const part = bodyParts[partIndex];
        if (!part) return;
        const selected = Math.max(0, Math.min(part.choices.length - 1, variant));
        part.choices.forEach((choice, i) => { choice.visible = i === selected; });
    };
    const applySkin = (family) => {
        const selected = Math.max(0, Math.min(Math.max(0, numSkinFamilies - 1), family));
        for (const mesh of meshes) {
            const textureIndex = skinRefs[selected * numSkinRef + mesh.textureIndex];
            const texture = textures[textureIndex];
            mesh.material.map = texture ? texture.tex : null;
            mesh.material.transparent = !!(texture && texture.masked);
            mesh.material.alphaTest = texture && texture.masked ? 0.5 : 0;
            mesh.material.needsUpdate = true;
        }
    };

    const info = {
        bones: numBones,
        sequences: numSeq,
        skinFamilies: numSkinFamilies,
        bodyParts: bodyParts.map((part) => part.choices.length),
        hitboxes: numHitboxes,
        attachments: numAttachments,
        textures: textures.length,
        externalTextures,
        meshes: meshes.length,
        triangles,
        texList: textures.map((t) => t.w + '×' + t.h),
    };

    return { group, info, seqs, pose, bodyParts, applyBody, applySkin, overlays };
}

function boot() {
    const stage = document.getElementById('mdl-stage');
    if (!stage) return;
    const $ = (id) => document.getElementById(id);
    const statusEl = $('mdl-status'), infoEl = $('mdl-info'), seqEl = $('mdl-seq');
    const favKey = 'speedmn.mdl.favs';
    const thumbKey = 'speedmn.mdl.thumbs';
    const readStore = (key) => {
        try { return JSON.parse(localStorage.getItem(key) || '{}'); } catch { return {}; }
    };
    const favs = new Set(Array.isArray(readStore(favKey)) ? readStore(favKey) : []);
    const saveFavs = () => localStorage.setItem(favKey, JSON.stringify([...favs]));
    const thumbs = readStore(thumbKey);

    const renderer = new THREE.WebGLRenderer({ antialias: true, preserveDrawingBuffer: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    stage.appendChild(renderer.domElement);
    const scene = new THREE.Scene();
    scene.background = new THREE.Color($('mdl-bg').value);
    scene.add(new THREE.GridHelper(200, 20, 0x2a3550, 0x1a2234));
    const ground = new THREE.Mesh(
        new THREE.PlaneGeometry(2000, 2000),
        new THREE.MeshStandardMaterial({ color: 0x101722, roughness: 1 }),
    );
    ground.rotation.x = -Math.PI / 2;
    ground.position.y = -0.5;
    ground.receiveShadow = true;
    scene.add(ground);
    scene.add(new THREE.HemisphereLight(0xd8e5ff, 0x28303a, 2));
    const keyLight = new THREE.DirectionalLight(0xffffff, 3.2);
    keyLight.position.set(80, 120, 80);
    keyLight.castShadow = true;
    keyLight.shadow.mapSize.set(1024, 1024);
    keyLight.shadow.camera.near = 1;
    keyLight.shadow.camera.far = 800;
    keyLight.shadow.camera.left = -300;
    keyLight.shadow.camera.right = 300;
    keyLight.shadow.camera.top = 300;
    keyLight.shadow.camera.bottom = -300;
    keyLight.shadow.camera.updateProjectionMatrix();
    scene.add(keyLight);
    const fillLight = new THREE.DirectionalLight(0x9abaff, 1.4);
    fillLight.position.set(-80, 40, -60);
    scene.add(fillLight);
    const camera = new THREE.PerspectiveCamera(45, 1, 0.1, 5000);
    camera.position.set(60, 40, 80);
    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.autoRotate = $('mdl-rotate').checked;
    controls.autoRotateSpeed = 2;

    let model = null, root = null, seqNo = 0, playing = true, clock = 0, currentFrame = -1, currentBtn = null, requestId = 0;
    let compare = null, compareRequestId = 0;

    const resize = () => {
        renderer.setSize(stage.clientWidth, stage.clientHeight);
        camera.aspect = stage.clientWidth / stage.clientHeight;
        camera.updateProjectionMatrix();
    };
    window.addEventListener('resize', resize);
    resize();

    renderer.setAnimationLoop((time, delta) => {
        const dt = Math.min(0.1, (delta || 16) / 1000);
        if (model && playing) {
            clock += dt;
            const seq = model.seqs[seqNo];
            if (seq && seq.frames > 1) {
                const frame = Math.floor(clock * seq.fps) % seq.frames;
                if (frame !== currentFrame) {
                    model.pose(seqNo, frame);
                    currentFrame = frame;
                }
            }
        }
        controls.update();
        renderer.render(scene, camera);
        if (compare) {
            if (compare.playing && compare.model) {
                compare.clock += dt;
                const seq = compare.model.seqs[compare.seqNo];
                if (seq && seq.frames > 1) {
                    const frame = Math.floor(compare.clock * seq.fps) % seq.frames;
                    if (frame !== compare.currentFrame) {
                        compare.model.pose(compare.seqNo, frame);
                        compare.currentFrame = frame;
                    }
                }
            }
            compare.controls.update();
            compare.renderer.render(compare.scene, compare.camera);
        }
    });

    const dispose = (obj) => obj.traverse((o) => {
        if (o.geometry) o.geometry.dispose();
        if (o.material) {
            o.material.map?.dispose();
            o.material.dispose();
        }
    });

    const applyWire = () => {
        root?.traverse((o) => { if (o.material && !Array.isArray(o.material)) o.material.wireframe = $('mdl-wire').checked; });
        if (compare?.root) compare.root.traverse((o) => { if (o.material && !Array.isArray(o.material)) o.material.wireframe = $('mdl-wire').checked; });
    };

    const saveThumbnail = (btn) => {
        if (thumbs[btn.dataset.mdlId]) return;
        try {
            const canvas = renderer.domElement;
            const image = canvas.toDataURL('image/webp', 0.45);
            const img = document.createElement('img');
            img.className = 'mdl-thumb';
            img.alt = '';
            img.loading = 'lazy';
            img.src = image;
            btn.prepend(img);
            thumbs[btn.dataset.mdlId] = image;
            const keys = Object.keys(thumbs);
            if (keys.length > 80) delete thumbs[keys[0]];
            localStorage.setItem(thumbKey, JSON.stringify(thumbs));
        } catch {
            // Browser storage quotas vary; the current model remains usable without a cached thumbnail.
        }
    };

    const restoreThumbnails = () => {
        for (const btn of document.querySelectorAll('[data-mdl-url]')) {
            const image = thumbs[btn.dataset.mdlId];
            if (!image) continue;
            const img = document.createElement('img');
            img.className = 'mdl-thumb';
            img.alt = '';
            img.loading = 'lazy';
            img.src = image;
            btn.prepend(img);
        }
    };

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
            currentFrame = 0;
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
                ['Hitbox / Attachment', i.hitboxes + ' / ' + i.attachments],
                ['Skin family', i.skinFamilies],
                ['Body хэсэг', i.bodyParts.length],
                ['Хэмжээ', [size.x, size.z, size.y].map((n) => n.toFixed(1)).join(' × ')],
            ].forEach(([k, v]) => {
                const row = document.createElement('div');
                const b = document.createElement('strong');
                b.textContent = k + ': ';
                row.append(b, String(v));
                infoEl.appendChild(row);
            });

            currentBtn = btn;
            $('mdl-skin').innerHTML = '';
            for (let family = 0; family < Math.max(1, i.skinFamilies); family++) {
                const option = document.createElement('option');
                option.value = family;
                option.textContent = 'Skin ' + (family + 1);
                $('mdl-skin').appendChild(option);
            }
            $('mdl-body').innerHTML = '';
            parsed.bodyParts.forEach((part, partIndex) => {
                if (part.choices.length < 2) return;
                for (let variant = 0; variant < part.choices.length; variant++) {
                    const option = document.createElement('option');
                    option.value = partIndex + ':' + variant;
                    option.textContent = 'Body ' + (partIndex + 1) + ' · ' + (variant + 1);
                    $('mdl-body').appendChild(option);
                }
            });
            $('mdl-skin').disabled = i.skinFamilies < 2;
            $('mdl-body').disabled = $('mdl-body').options.length === 0;
            parsed.applySkin(0);
            parsed.applyBody(0, 0);
            parsed.overlays.visible = $('mdl-overlays').checked;
            $('mdl-fav').textContent = favs.has(btn.dataset.mdlId) ? '★ Дуртайгаас хасах' : '☆ Дуртайд нэмэх';
            $('mdl-fav').disabled = false;
            $('mdl-download').href = url;
            $('mdl-download').hidden = false;
            statusEl.textContent = label;
            history.replaceState(null, '', '?m=' + encodeURIComponent(btn.dataset.mdlId));
            renderer.render(scene, camera);
            saveThumbnail(btn);
        } catch (e) {
            if (mine === requestId) statusEl.textContent = 'Алдаа: ' + e.message;
        }
    };

    const buttons = [...document.querySelectorAll('[data-mdl-url]')];
    restoreThumbnails();

    const loadCompare = async (btn) => {
        if (!compare || !btn) return;
        const mine = ++compareRequestId;
        $('mdl-compare-status').textContent = 'Ачаалж байна: ' + btn.dataset.mdlLabel;
        try {
            const buf = await fetchBuffer(btn.dataset.mdlUrl);
            let texBuf = null;
            if (new DataView(buf).getInt32(180, true) === 0) {
                texBuf = await fetchBuffer(btn.dataset.mdlUrl.replace(/\.mdl$/i, 'T.mdl')).catch(() => null);
            }
            if (mine !== compareRequestId) return;
            const parsed = parseMdl(buf, texBuf);
            if (compare.root) {
                compare.scene.remove(compare.root);
                dispose(compare.root);
            }
            compare.model = parsed;
            compare.seqNo = Math.max(0, parsed.seqs.findIndex((s) => /idle/i.test(s.label)));
            compare.clock = 0;
            compare.currentFrame = -1;
            compare.root = new THREE.Group();
            compare.root.add(parsed.group);
            compare.root.rotation.x = -Math.PI / 2;
            compare.scene.add(compare.root);
            parsed.pose(compare.seqNo, 0);
            compare.currentFrame = 0;
            compare.root.updateMatrixWorld(true);
            const box = new THREE.Box3().setFromObject(compare.root);
            const size = box.getSize(new THREE.Vector3());
            const center = box.getCenter(new THREE.Vector3());
            compare.root.position.sub(center);
            compare.root.position.y += size.y / 2;
            const distance = Math.max(size.x, size.y, size.z, 1) * 1.8;
            compare.camera.position.set(distance, distance * 0.6, distance);
            compare.controls.target.set(0, size.y / 2, 0);
            compare.controls.update();
            compare.scene.background = new THREE.Color($('mdl-bg').value);
            $('mdl-compare-status').textContent = btn.dataset.mdlLabel;
            compare.renderer.render(compare.scene, compare.camera);
        } catch (e) {
            if (mine === compareRequestId) $('mdl-compare-status').textContent = 'Алдаа: ' + e.message;
        }
    };

    $('mdl-compare-toggle').addEventListener('click', () => {
        const wrap = $('mdl-compare');
        wrap.hidden = !wrap.hidden;
        if (!wrap.hidden) {
            if (!compare) {
                const compareStage = $('mdl-compare-stage');
                const compareRenderer = new THREE.WebGLRenderer({ antialias: true, preserveDrawingBuffer: true });
                compareRenderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
                compareRenderer.shadowMap.enabled = true;
                compareRenderer.shadowMap.type = THREE.PCFSoftShadowMap;
                compareStage.appendChild(compareRenderer.domElement);
                const compareScene = new THREE.Scene();
                compareScene.background = new THREE.Color($('mdl-bg').value);
                compareScene.add(new THREE.GridHelper(200, 20, 0x2a3550, 0x1a2234));
                const compareGround = new THREE.Mesh(new THREE.PlaneGeometry(2000, 2000), new THREE.MeshStandardMaterial({ color: 0x101722, roughness: 1 }));
                compareGround.rotation.x = -Math.PI / 2;
                compareGround.position.y = -0.5;
                compareGround.receiveShadow = true;
                compareScene.add(compareGround, new THREE.HemisphereLight(0xd8e5ff, 0x28303a, 2));
                const compareLight = new THREE.DirectionalLight(0xffffff, 3.2);
                compareLight.position.set(80, 120, 80);
                compareLight.castShadow = true;
                compareLight.shadow.mapSize.set(1024, 1024);
                compareScene.add(compareLight);
                const compareCamera = new THREE.PerspectiveCamera(45, 1, 0.1, 5000);
                const compareControls = new OrbitControls(compareCamera, compareRenderer.domElement);
                compareControls.enableDamping = true;
                compareControls.autoRotate = $('mdl-rotate').checked;
                compareControls.autoRotateSpeed = 2;
                compare = { renderer: compareRenderer, scene: compareScene, camera: compareCamera, controls: compareControls, root: null, model: null, seqNo: 0, clock: 0, currentFrame: -1, playing: true };
                const resizeCompare = () => {
                    if (compareStage.clientWidth === 0) return;
                    compareRenderer.setSize(compareStage.clientWidth, compareStage.clientHeight);
                    compareCamera.aspect = compareStage.clientWidth / compareStage.clientHeight;
                    compareCamera.updateProjectionMatrix();
                };
                window.addEventListener('resize', resizeCompare);
                resizeCompare();
                const select = $('mdl-compare-select');
                select.innerHTML = '';
                buttons.forEach((btn) => {
                    const option = document.createElement('option');
                    option.value = btn.dataset.mdlId;
                    option.textContent = btn.dataset.mdlLabel;
                    select.appendChild(option);
                });
                select.addEventListener('change', () => loadCompare(buttons.find((btn) => btn.dataset.mdlId === select.value)));
            }
            const firstCompare = buttons.find((btn) => btn.dataset.mdlId !== currentBtn?.dataset.mdlId) || currentBtn;
            $('mdl-compare-select').value = firstCompare?.dataset.mdlId || '';
            loadCompare(firstCompare);
        }
        $('mdl-compare-toggle').textContent = wrap.hidden ? 'Харьцуулалт' : 'Харьцуулалтыг хаах';
    });

    $('mdl-screenshot').addEventListener('click', () => {
        renderer.render(scene, camera);
        renderer.domElement.toBlob((blob) => {
            if (!blob) {
                statusEl.textContent = 'PNG үүсгэж чадсангүй.';
                return;
            }
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = (currentBtn?.dataset.mdlId || 'model').replace(/[^\w.-]+/g, '_') + '.png';
            link.click();
            setTimeout(() => URL.revokeObjectURL(link.href), 1000);
        }, 'image/png');
    });
    $('mdl-share').addEventListener('click', async () => {
        const url = location.href;
        try {
            if (navigator.share) {
                await navigator.share({ title: currentBtn?.dataset.mdlLabel || 'Speed.mn model', url });
            } else {
                await navigator.clipboard.writeText(url);
                statusEl.textContent = 'Model-ийн холбоосыг хууллаа. Discord-д paste хийнэ үү.';
            }
        } catch (e) {
            if (e.name !== 'AbortError') statusEl.textContent = 'Хуваалцаж чадсангүй: ' + e.message;
        }
    });
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

    $('mdl-rotate').addEventListener('change', (e) => {
        controls.autoRotate = e.target.checked;
        if (compare) compare.controls.autoRotate = e.target.checked;
    });
    $('mdl-wire').addEventListener('change', applyWire);
    $('mdl-bg').addEventListener('input', (e) => {
        scene.background = new THREE.Color(e.target.value);
        if (compare) compare.scene.background = new THREE.Color(e.target.value);
    });
    $('mdl-skin').addEventListener('change', (e) => model?.applySkin(Number(e.target.value)));
    $('mdl-body').addEventListener('change', (e) => {
        const [part, variant] = e.target.value.split(':').map(Number);
        model?.applyBody(part, variant);
    });
    $('mdl-overlays').addEventListener('change', (e) => {
        if (model) model.overlays.visible = e.target.checked;
    });
    $('mdl-play').addEventListener('click', (e) => {
        playing = !playing;
        e.target.textContent = playing ? '❚❚ Зогсоох' : '▶ Тоглуулах';
    });
    seqEl.addEventListener('change', () => {
        seqNo = Number(seqEl.value);
        clock = 0;
        model?.pose(seqNo, 0);
        currentFrame = 0;
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
