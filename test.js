// voxelizer-optimized.js - Memory-efficient version (1-100MB target)
const sharp = require('sharp');

class GLBExtractor {
    constructor(buffer) {
        const magic = buffer.readUInt32LE(0);
        if (magic !== 0x46546C67) throw new Error("Not a valid GLB file");
        
        const jsonLength = buffer.readUInt32LE(12);
        const jsonData = buffer.slice(20, 20 + jsonLength).toString('utf-8');
        this.gltf = JSON.parse(jsonData);
        
        this.binData = buffer.slice(20 + jsonLength + 8);
        this.textures = [];
    }
    
    async extractTextures() {
        if (!this.gltf.images) return;
        
        for (let i = 0; i < this.gltf.images.length; i++) {
            const image = this.gltf.images[i];
            
            if (image.bufferView !== undefined) {
                const bufferView = this.gltf.bufferViews[image.bufferView];
                const offset = bufferView.byteOffset || 0;
                const length = bufferView.byteLength;
                
                const imageData = this.binData.slice(offset, offset + length);
                const decoded = await this.decodePNG(imageData);
                this.textures.push(decoded);
            }
        }
    }
    
    async decodePNG(buffer) {
        try {
            const image = sharp(buffer);
            const metadata = await image.metadata();
            const { data, info } = await image.raw().toBuffer({ resolveWithObject: true });
            
            return { width: info.width, height: info.height, data: data };
        } catch (e) {
            return null;
        }
    }
    
    getMeshData() {
        const vertexData = [];
        const colorData = [];
        const uvData = [];
        const textureIndices = [];
        
        let triangleCount = 0;
        
        for (let meshIdx = 0; meshIdx < this.gltf.meshes.length; meshIdx++) {
            const mesh = this.gltf.meshes[meshIdx];
            
            for (const primitive of mesh.primitives) {
                const positions = this._readAccessor(this.gltf.accessors[primitive.attributes.POSITION]);
                const texCoords = primitive.attributes.TEXCOORD_0 ? 
                    this._readAccessor(this.gltf.accessors[primitive.attributes.TEXCOORD_0]) : null;
                const indices = primitive.indices !== undefined ? 
                    this._readAccessor(this.gltf.accessors[primitive.indices]) : null;
                
                let material = null;
                let textureIndex = -1;
                
                if (primitive.material !== undefined) {
                    material = this.gltf.materials[primitive.material];
                    
                    if (material.pbrMetallicRoughness?.baseColorTexture) {
                        const texIdx = material.pbrMetallicRoughness.baseColorTexture.index;
                        textureIndex = this.gltf.textures[texIdx].source;
                    }
                }
                
                const defaultColor = material?.pbrMetallicRoughness?.baseColorFactor || [0.8, 0.8, 0.8, 1.0];
                const color = [
                    Math.floor(defaultColor[0] * 255),
                    Math.floor(defaultColor[1] * 255),
                    Math.floor(defaultColor[2] * 255)
                ];
                
                if (indices) {
                    for (let i = 0; i < indices.length; i += 3) {
                        const i0 = indices[i], i1 = indices[i + 1], i2 = indices[i + 2];
                        
                        vertexData.push(
                            positions[i0 * 3], positions[i0 * 3 + 1], positions[i0 * 3 + 2],
                            positions[i1 * 3], positions[i1 * 3 + 1], positions[i1 * 3 + 2],
                            positions[i2 * 3], positions[i2 * 3 + 1], positions[i2 * 3 + 2]
                        );
                        
                        colorData.push(color[0], color[1], color[2]);
                        
                        if (texCoords && textureIndex !== -1) {
                            uvData.push(
                                texCoords[i0 * 2], texCoords[i0 * 2 + 1],
                                texCoords[i1 * 2], texCoords[i1 * 2 + 1],
                                texCoords[i2 * 2], texCoords[i2 * 2 + 1]
                            );
                            textureIndices.push(textureIndex);
                        } else {
                            uvData.push(0, 0, 0, 0, 0, 0);
                            textureIndices.push(-1);
                        }
                        
                        triangleCount++;
                    }
                }
            }
        }
        
        console.log(`Loaded ${triangleCount} triangles`);
        
        return {
            vertices: new Float32Array(vertexData),
            colors: new Uint8Array(colorData),
            uvs: new Float32Array(uvData),
            textureIndices: new Int8Array(textureIndices),
            triangleCount: triangleCount
        };
    }
    
    sampleTexture(texIndex, u, v) {
        const tex = this.textures[texIndex];
        if (!tex || !tex.data) return null;
        
        u = Math.max(0, Math.min(1, u));
        v = Math.max(0, Math.min(1, v));
        
        const x = Math.floor(u * (tex.width - 1));
        const y = Math.floor(v * (tex.height - 1));
        
        const channels = tex.data.length / (tex.width * tex.height);
        const idx = (y * tex.width + x) * channels;
        
        return [
            tex.data[idx],
            tex.data[idx + 1],
            tex.data[idx + 2]
        ];
    }
    
    _readAccessor(accessor) {
        const bufferView = this.gltf.bufferViews[accessor.bufferView];
        const offset = (bufferView.byteOffset || 0) + (accessor.byteOffset || 0);
        
        const typeSize = { SCALAR: 1, VEC2: 2, VEC3: 3, VEC4: 4 }[accessor.type];
        const count = accessor.count;
        
        const componentType = accessor.componentType;
        let data;
        
        if (componentType === 5126) {
            data = new Float32Array(this.binData.buffer, this.binData.byteOffset + offset, count * typeSize);
        } else if (componentType === 5123) {
            data = new Uint16Array(this.binData.buffer, this.binData.byteOffset + offset, count * typeSize);
        } else if (componentType === 5125) {
            data = new Uint32Array(this.binData.buffer, this.binData.byteOffset + offset, count * typeSize);
        } else if (componentType === 5121) {
            data = new Uint8Array(this.binData.buffer, this.binData.byteOffset + offset, count * typeSize);
        }
        
        return data;
    }
}

// Lightweight BVH - ใช้ indices แทน triangle objects
class CompactBVHNode {
    constructor(triIndices, start, end, vertices) {
        this.triIndices = triIndices;
        this.start = start;
        this.end = end;
        
        if (end - start === 1) {
            // Leaf node - เก็บแค่ index
            this.triIndex = triIndices[start];
            this.bbox = this._triangleBBox(triIndices[start], vertices);
            this.left = null;
            this.right = null;
        } else {
            // Internal node
            const bbox = this._computeBBox(triIndices, start, end, vertices);
            const axis = this._longestAxis(bbox);
            
            // Sort in place
            const slice = triIndices.slice(start, end);
            slice.sort((a, b) => {
                const centA = this._getTriangleCentroid(a, vertices, axis);
                const centB = this._getTriangleCentroid(b, vertices, axis);
                return centA - centB;
            });
            
            for (let i = 0; i < slice.length; i++) {
                triIndices[start + i] = slice[i];
            }
            
            const mid = Math.floor((start + end) / 2);
            this.left = new CompactBVHNode(triIndices, start, mid, vertices);
            this.right = new CompactBVHNode(triIndices, mid, end, vertices);
            this.bbox = this._mergeBBox(this.left.bbox, this.right.bbox);
            this.triIndex = -1;
        }
    }
    
    _getTriangleCentroid(triIdx, vertices, axis) {
        const vIdx = triIdx * 9;
        return (vertices[vIdx + axis] + vertices[vIdx + 3 + axis] + vertices[vIdx + 6 + axis]) / 3;
    }
    
    _triangleBBox(triIdx, vertices) {
        const vIdx = triIdx * 9;
        const v0x = vertices[vIdx], v0y = vertices[vIdx + 1], v0z = vertices[vIdx + 2];
        const v1x = vertices[vIdx + 3], v1y = vertices[vIdx + 4], v1z = vertices[vIdx + 5];
        const v2x = vertices[vIdx + 6], v2y = vertices[vIdx + 7], v2z = vertices[vIdx + 8];
        
        return {
            minX: Math.min(v0x, v1x, v2x),
            minY: Math.min(v0y, v1y, v2y),
            minZ: Math.min(v0z, v1z, v2z),
            maxX: Math.max(v0x, v1x, v2x),
            maxY: Math.max(v0y, v1y, v2y),
            maxZ: Math.max(v0z, v1z, v2z)
        };
    }
    
    _computeBBox(triIndices, start, end, vertices) {
        let minX = Infinity, minY = Infinity, minZ = Infinity;
        let maxX = -Infinity, maxY = -Infinity, maxZ = -Infinity;
        
        for (let i = start; i < end; i++) {
            const vIdx = triIndices[i] * 9;
            const v0x = vertices[vIdx], v0y = vertices[vIdx + 1], v0z = vertices[vIdx + 2];
            const v1x = vertices[vIdx + 3], v1y = vertices[vIdx + 4], v1z = vertices[vIdx + 5];
            const v2x = vertices[vIdx + 6], v2y = vertices[vIdx + 7], v2z = vertices[vIdx + 8];
            
            minX = Math.min(minX, v0x, v1x, v2x);
            minY = Math.min(minY, v0y, v1y, v2y);
            minZ = Math.min(minZ, v0z, v1z, v2z);
            maxX = Math.max(maxX, v0x, v1x, v2x);
            maxY = Math.max(maxY, v0y, v1y, v2y);
            maxZ = Math.max(maxZ, v0z, v1z, v2z);
        }
        
        return { minX, minY, minZ, maxX, maxY, maxZ };
    }
    
    _longestAxis(bbox) {
        const dx = bbox.maxX - bbox.minX;
        const dy = bbox.maxY - bbox.minY;
        const dz = bbox.maxZ - bbox.minZ;
        return dx > dy ? (dx > dz ? 0 : 2) : (dy > dz ? 1 : 2);
    }
    
    _mergeBBox(a, b) {
        return {
            minX: Math.min(a.minX, b.minX),
            minY: Math.min(a.minY, b.minY),
            minZ: Math.min(a.minZ, b.minZ),
            maxX: Math.max(a.maxX, b.maxX),
            maxY: Math.max(a.maxY, b.maxY),
            maxZ: Math.max(a.maxZ, b.maxZ)
        };
    }
    
    // Intersection ที่ return แค่ข้อมูลจำเป็น
    intersectAll(ro, rd, vertices, colors, uvs, texIndices, results = []) {
        if (!this._intersectBBox(ro, rd, this.bbox)) {
            return results;
        }
        
        if (this.triIndex !== -1) {
            const hit = this._rayTriangleIntersect(ro, rd, this.triIndex, vertices);
            if (hit) {
                results.push({
                    triIndex: this.triIndex,
                    t: hit.t,
                    u: hit.u,
                    v: hit.v
                });
            }
            return results;
        }
        
        if (this.left) this.left.intersectAll(ro, rd, vertices, colors, uvs, texIndices, results);
        if (this.right) this.right.intersectAll(ro, rd, vertices, colors, uvs, texIndices, results);
        
        return results;
    }
    
    _intersectBBox(ro, rd, bbox) {
        const invDirX = 1.0 / (rd[0] || 1e-10);
        const invDirY = 1.0 / (rd[1] || 1e-10);
        const invDirZ = 1.0 / (rd[2] || 1e-10);
        
        const t1 = (bbox.minX - ro[0]) * invDirX;
        const t2 = (bbox.maxX - ro[0]) * invDirX;
        const t3 = (bbox.minY - ro[1]) * invDirY;
        const t4 = (bbox.maxY - ro[1]) * invDirY;
        const t5 = (bbox.minZ - ro[2]) * invDirZ;
        const t6 = (bbox.maxZ - ro[2]) * invDirZ;
        
        const tmin = Math.max(Math.min(t1, t2), Math.min(t3, t4), Math.min(t5, t6));
        const tmax = Math.min(Math.max(t1, t2), Math.max(t3, t4), Math.max(t5, t6));
        
        return tmax >= Math.max(0, tmin);
    }
    
    _rayTriangleIntersect(ro, rd, triIdx, vertices) {
        const EPSILON = 1e-8;
        const vIdx = triIdx * 9;
        
        const v0x = vertices[vIdx], v0y = vertices[vIdx + 1], v0z = vertices[vIdx + 2];
        const v1x = vertices[vIdx + 3], v1y = vertices[vIdx + 4], v1z = vertices[vIdx + 5];
        const v2x = vertices[vIdx + 6], v2y = vertices[vIdx + 7], v2z = vertices[vIdx + 8];
        
        const e1x = v1x - v0x, e1y = v1y - v0y, e1z = v1z - v0z;
        const e2x = v2x - v0x, e2y = v2y - v0y, e2z = v2z - v0z;
        
        const hx = rd[1] * e2z - rd[2] * e2y;
        const hy = rd[2] * e2x - rd[0] * e2z;
        const hz = rd[0] * e2y - rd[1] * e2x;
        
        const a = e1x * hx + e1y * hy + e1z * hz;
        if (Math.abs(a) < EPSILON) return null;
        
        const f = 1.0 / a;
        const sx = ro[0] - v0x, sy = ro[1] - v0y, sz = ro[2] - v0z;
        const u = f * (sx * hx + sy * hy + sz * hz);
        
        if (u < 0.0 || u > 1.0) return null;
        
        const qx = sy * e1z - sz * e1y;
        const qy = sz * e1x - sx * e1z;
        const qz = sx * e1y - sy * e1x;
        
        const v = f * (rd[0] * qx + rd[1] * qy + rd[2] * qz);
        if (v < 0.0 || u + v > 1.0) return null;
        
        const t = f * (e2x * qx + e2y * qy + e2z * qz);
        
        if (t > EPSILON) {
            return { t, u, v };
        }
        
        return null;
    }
}

class ColorQuantizer {
    constructor(maxColors = 255) {
        this.maxColors = maxColors;
        this.palette = [];
        this.colorMap = new Map();
        this.colorSamples = [];
    }
    
    addColorSample(r, g, b) {
        this.colorSamples.push([r, g, b]);
    }
    
    buildPalette() {
        if (this.colorSamples.length === 0) return;
        
        const uniqueColors = new Map();
        for (const [r, g, b] of this.colorSamples) {
            const key = `${r},${g},${b}`;
            uniqueColors.set(key, (uniqueColors.get(key) || 0) + 1);
        }
        
        if (uniqueColors.size <= this.maxColors) {
            let idx = 1;
            for (const [key, count] of uniqueColors) {
                const [r, g, b] = key.split(',').map(Number);
                this.palette.push([r, g, b]);
                this.colorMap.set(key, idx++);
            }
        } else {
            const colors = Array.from(uniqueColors.entries()).map(([key, count]) => {
                const [r, g, b] = key.split(',').map(Number);
                return { r, g, b, count };
            });
            
            this.palette = this.medianCut(colors, this.maxColors);
            
            for (let i = 0; i < this.palette.length; i++) {
                const [r, g, b] = this.palette[i];
                const key = `${r},${g},${b}`;
                this.colorMap.set(key, i + 1);
            }
        }
        
        // ลบ samples ทิ้งเพื่อประหยัด memory
        this.colorSamples = null;
    }
    
    medianCut(colors, maxColors) {
        const buckets = [colors];
        
        while (buckets.length < maxColors) {
            buckets.sort((a, b) => b.length - a.length);
            const bucket = buckets.shift();
            
            if (bucket.length === 1) {
                buckets.push(bucket);
                break;
            }
            
            const ranges = {
                r: { min: 255, max: 0 },
                g: { min: 255, max: 0 },
                b: { min: 255, max: 0 }
            };
            
            for (const c of bucket) {
                ranges.r.min = Math.min(ranges.r.min, c.r);
                ranges.r.max = Math.max(ranges.r.max, c.r);
                ranges.g.min = Math.min(ranges.g.min, c.g);
                ranges.g.max = Math.max(ranges.g.max, c.g);
                ranges.b.min = Math.min(ranges.b.min, c.b);
                ranges.b.max = Math.max(ranges.b.max, c.b);
            }
            
            const rRange = ranges.r.max - ranges.r.min;
            const gRange = ranges.g.max - ranges.g.min;
            const bRange = ranges.b.max - ranges.b.min;
            
            const sortKey = rRange >= gRange && rRange >= bRange ? 'r' :
                           gRange >= bRange ? 'g' : 'b';
            
            bucket.sort((a, b) => a[sortKey] - b[sortKey]);
            
            const mid = Math.floor(bucket.length / 2);
            buckets.push(bucket.slice(0, mid));
            buckets.push(bucket.slice(mid));
        }
        
        return buckets.map(bucket => {
            let totalR = 0, totalG = 0, totalB = 0, totalCount = 0;
            
            for (const c of bucket) {
                totalR += c.r * c.count;
                totalG += c.g * c.count;
                totalB += c.b * c.count;
                totalCount += c.count;
            }
            
            return [
                Math.round(totalR / totalCount),
                Math.round(totalG / totalCount),
                Math.round(totalB / totalCount)
            ];
        });
    }
    
    addColor(r, g, b) {
        const key = `${r},${g},${b}`;
        
        if (this.colorMap.has(key)) {
            return this.colorMap.get(key);
        }
        
        let minDist = Infinity;
        let bestIdx = 1;
        
        for (let i = 0; i < this.palette.length; i++) {
            const [pr, pg, pb] = this.palette[i];
            const dist = (pr - r) ** 2 + (pg - g) ** 2 + (pb - b) ** 2;
            if (dist < minDist) {
                minDist = dist;
                bestIdx = i + 1;
            }
        }
        
        this.colorMap.set(key, bestIdx);
        return bestIdx;
    }
    
    getPalette() {
        const pal = new Uint8Array(256 * 4);
        for (let i = 0; i < this.palette.length; i++) {
            const [r, g, b] = this.palette[i];
            pal[(i + 1) * 4] = r;
            pal[(i + 1) * 4 + 1] = g;
            pal[(i + 1) * 4 + 2] = b;
            pal[(i + 1) * 4 + 3] = 255;
        }
        return pal;
    }
}

class VOXWriter {
    constructor(quantizer) {
        this.voxels = new Map();
        this.quantizer = quantizer;
    }
    
    addVoxel(x, y, z, r, g, b) {
        const key = `${x},${y},${z}`;
        const colorIdx = this.quantizer.addColor(r, g, b);
        this.voxels.set(key, colorIdx);
    }
    
    smoothColors(radius = 1) {
        if (radius === 0) return;
        
        const smoothed = new Map();
        const colorCache = new Map();
        
        for (const [key, idx] of this.quantizer.colorMap) {
            const [r, g, b] = key.split(',').map(Number);
            colorCache.set(idx, [r, g, b]);
        }
        
        for (const [key, colorIdx] of this.voxels) {
            const [x, y, z] = key.split(',').map(Number);
            
            let totalR = 0, totalG = 0, totalB = 0, count = 0;
            
            for (let dx = -radius; dx <= radius; dx++) {
                for (let dy = -radius; dy <= radius; dy++) {
                    for (let dz = -radius; dz <= radius; dz++) {
                        const nKey = `${x + dx},${y + dy},${z + dz}`;
                        const nColorIdx = this.voxels.get(nKey);
                        
                        if (nColorIdx) {
                            const color = colorCache.get(nColorIdx);
                            if (color) {
                                const dist = Math.sqrt(dx*dx + dy*dy + dz*dz);
                                const weight = dist === 0 ? 2 : 1 / (1 + dist);
                                
                                totalR += color[0] * weight;
                                totalG += color[1] * weight;
                                totalB += color[2] * weight;
                                count += weight;
                            }
                        }
                    }
                }
            }
            
            if (count > 0) {
                const avgR = Math.round(totalR / count);
                const avgG = Math.round(totalG / count);
                const avgB = Math.round(totalB / count);
                
                smoothed.set(key, this.quantizer.addColor(avgR, avgG, avgB));
            } else {
                smoothed.set(key, colorIdx);
            }
        }
        
        this.voxels = smoothed;
    }
    
    write(size) {
        const chunks = this._buildChunks(size);
        
        const header = Buffer.alloc(8);
        header.write('VOX ', 0);
        header.writeUInt32LE(150, 4);
        
        const mainHeader = Buffer.alloc(12);
        mainHeader.write('MAIN', 0);
        mainHeader.writeUInt32LE(0, 4);
        mainHeader.writeUInt32LE(chunks.length, 8);
        
        return Buffer.concat([header, mainHeader, chunks]);
    }
    
    _buildChunks(size) {
        const sizeChunk = Buffer.alloc(24);
        sizeChunk.write('SIZE', 0);
        sizeChunk.writeUInt32LE(12, 4);
        sizeChunk.writeUInt32LE(0, 8);
        sizeChunk.writeUInt32LE(size[0], 12);
        sizeChunk.writeUInt32LE(size[1], 16);
        sizeChunk.writeUInt32LE(size[2], 20);
        
        const voxelArray = Array.from(this.voxels.entries());
        const xyziData = Buffer.alloc(4 + voxelArray.length * 4);
        xyziData.writeUInt32LE(voxelArray.length, 0);
        
        for (let i = 0; i < voxelArray.length; i++) {
            const [pos, colorIdx] = voxelArray[i];
            const [x, y, z] = pos.split(',').map(Number);
            const offset = 4 + i * 4;
            xyziData[offset] = x;
            xyziData[offset + 1] = y;
            xyziData[offset + 2] = z;
            xyziData[offset + 3] = colorIdx;
        }
        
        const xyziChunk = Buffer.alloc(12 + xyziData.length);
        xyziChunk.write('XYZI', 0);
        xyziChunk.writeUInt32LE(xyziData.length, 4);
        xyziChunk.writeUInt32LE(0, 8);
        xyziData.copy(xyziChunk, 12);
        
        const palette = this.quantizer.getPalette();
        const rgbaData = Buffer.alloc(1024);
        for (let i = 1; i < 256; i++) {
            rgbaData[i * 4 - 4] = palette[i * 4];
            rgbaData[i * 4 - 3] = palette[i * 4 + 1];
            rgbaData[i * 4 - 2] = palette[i * 4 + 2];
            rgbaData[i * 4 - 1] = palette[i * 4 + 3];
        }
        
        const rgbaChunk = Buffer.alloc(12 + 1024);
        rgbaChunk.write('RGBA', 0);
        rgbaChunk.writeUInt32LE(1024, 4);
        rgbaChunk.writeUInt32LE(0, 8);
        rgbaData.copy(rgbaChunk, 12);
        
        return Buffer.concat([sizeChunk, xyziChunk, rgbaChunk]);
    }
    
    getVoxelData() {
        const voxelArray = Array.from(this.voxels.entries()).map(([pos, colorIdx]) => {
            const [x, y, z] = pos.split(',').map(Number);
            const color = this.quantizer.palette[colorIdx - 1];
            return { x, y, z, colorIdx, color };
        });
        
        return {
            voxels: voxelArray,
            palette: this.quantizer.palette
        };
    }
}

function calculateTriangleNormal(v0, v1, v2) {
    const e1x = v1[0] - v0[0], e1y = v1[1] - v0[1], e1z = v1[2] - v0[2];
    const e2x = v2[0] - v0[0], e2y = v2[1] - v0[1], e2z = v2[2] - v0[2];
    
    const nx = e1y * e2z - e1z * e2y;
    const ny = e1z * e2x - e1x * e2z;
    const nz = e1x * e2y - e1y * e2x;
    
    const len = Math.sqrt(nx * nx + ny * ny + nz * nz);
    
    if (len < 1e-8) return [0, 0, 1];
    
    return [nx / len, ny / len, nz / len];
}

function voxelizeMesh(meshData, resolution, extractor, smoothRadius, progressCallback) {
    const { vertices, colors, uvs, textureIndices, triangleCount } = meshData;
    
    console.log(`Starting voxelization: ${triangleCount} triangles, resolution ${resolution}`);
    
    // คำนวณ bounding box
    let minX = Infinity, minY = Infinity, minZ = Infinity;
    let maxX = -Infinity, maxY = -Infinity, maxZ = -Infinity;
    
    for (let i = 0; i < triangleCount; i++) {
        const vIdx = i * 9;
        for (let j = 0; j < 3; j++) {
            const x = vertices[vIdx + j * 3];
            const y = vertices[vIdx + j * 3 + 1];
            const z = vertices[vIdx + j * 3 + 2];
            minX = Math.min(minX, x);
            minY = Math.min(minY, y);
            minZ = Math.min(minZ, z);
            maxX = Math.max(maxX, x);
            maxY = Math.max(maxY, y);
            maxZ = Math.max(maxZ, z);
        }
    }
    
    const sizeX = maxX - minX, sizeY = maxY - minY, sizeZ = maxZ - minZ;
    const scale = (resolution - 1) / Math.max(sizeX, sizeY, sizeZ);
    
    // Scale vertices in place
    const scaledVertices = new Float32Array(vertices.length);
    for (let i = 0; i < vertices.length; i += 3) {
        scaledVertices[i] = (vertices[i] - minX) * scale;
        scaledVertices[i + 1] = (vertices[i + 1] - minY) * scale;
        scaledVertices[i + 2] = (vertices[i + 2] - minZ) * scale;
    }
    
    // สร้าง BVH ด้วย indices
    const triIndices = new Uint32Array(triangleCount);
    for (let i = 0; i < triangleCount; i++) {
        triIndices[i] = i;
    }
    
    console.log('Building BVH...');
    const bvh = new CompactBVHNode(triIndices, 0, triangleCount, scaledVertices);
    
    const quantizer = new ColorQuantizer(255);
    const gridSize = Math.min(Math.ceil(resolution) + 1, 256);
    
    // Ray directions: 6 ทิศทางหลัก + 4 ทิศทางแนวทแยงที่สำคัญ (10 ทิศทาง)
    const rayDirs = [
        [1, 0, 0], [-1, 0, 0],      // ±X
        [0, 1, 0], [0, -1, 0],      // ±Y
        [0, 0, 1], [0, 0, -1],      // ±Z
        [0.707, 0.707, 0],          // XY diagonal
        [0.707, 0, 0.707],          // XZ diagonal
        [0, 0.707, 0.707],          // YZ diagonal
        [0.577, 0.577, 0.577]       // XYZ diagonal (corner)
    ];
    
    console.log('Pass 1: Collecting color samples...');
    
    // Pass 1: Color sampling (แบบ memory-efficient)
    let sampledColors = 0;
    const maxSamples = 30000; // ลดลงจาก 50000
    const sampleStep = Math.max(1, Math.floor(gridSize / Math.sqrt(maxSamples / rayDirs.length)));
    
    // ใช้ Set แทน array เพื่อ deduplicate สีอัตโนมัติ
    const colorSet = new Set();
    
    for (const dir of rayDirs) {
        for (let i = 0; i < gridSize; i += sampleStep) {
            for (let j = 0; j < gridSize; j += sampleStep) {
                const absMax = Math.max(Math.abs(dir[0]), Math.abs(dir[1]), Math.abs(dir[2]));
                let ro;
                
                if (absMax === Math.abs(dir[0])) {
                    ro = [dir[0] > 0 ? -1 : gridSize, i, j];
                } else if (absMax === Math.abs(dir[1])) {
                    ro = [i, dir[1] > 0 ? -1 : gridSize, j];
                } else {
                    ro = [i, j, dir[2] > 0 ? -1 : gridSize];
                }
                
                const hits = bvh.intersectAll(ro, dir, scaledVertices, colors, uvs, textureIndices);
                
                for (let h = 0; h < Math.min(2, hits.length); h++) {
                    const hit = hits[h];
                    const triIdx = hit.triIndex;
                    const cIdx = triIdx * 3;
                    
                    let color = [colors[cIdx], colors[cIdx + 1], colors[cIdx + 2]];
                    
                    if (textureIndices[triIdx] !== -1 && extractor.textures[textureIndices[triIdx]]) {
                        const uvIdx = triIdx * 6;
                        const w = 1.0 - hit.u - hit.v;
                        const interpU = Math.max(0, Math.min(1, w * uvs[uvIdx] + hit.u * uvs[uvIdx + 2] + hit.v * uvs[uvIdx + 4]));
                        const interpV = Math.max(0, Math.min(1, w * uvs[uvIdx + 1] + hit.u * uvs[uvIdx + 3] + hit.v * uvs[uvIdx + 5]));
                        
                        const sampledColor = extractor.sampleTexture(textureIndices[triIdx], interpU, interpV);
                        if (sampledColor) {
                            color = sampledColor;
                        }
                    }
                    
                    // Pack RGB เป็น integer เดียวเพื่อประหยัด memory
                    const packed = (color[0] << 16) | (color[1] << 8) | color[2];
                    colorSet.add(packed);
                    sampledColors++;
                    
                    if (sampledColors >= maxSamples) break;
                }
                
                if (sampledColors >= maxSamples) break;
            }
            if (sampledColors >= maxSamples) break;
        }
        if (sampledColors >= maxSamples) break;
    }
    
    // แปลง Set กลับเป็น array และส่งให้ quantizer
    for (const packed of colorSet) {
        const r = (packed >> 16) & 0xFF;
        const g = (packed >> 8) & 0xFF;
        const b = packed & 0xFF;
        quantizer.addColorSample(r, g, b);
    }
    
    console.log(`Sampled ${colorSet.size} unique colors from ${sampledColors} samples`);
    colorSet.clear(); // ล้าง Set ทิ้ง
    
    quantizer.buildPalette();
    console.log(`Built palette: ${quantizer.palette.length} colors`);
    progressCallback(30);
    
    // Pass 2: Voxelization (streaming แบบ per-direction)
    const voxWriter = new VOXWriter(quantizer);
    
    let rayCount = 0;
    const totalRays = rayDirs.length;
    
    for (const dir of rayDirs) {
        rayCount++;
        
        // Process แบบ streaming per direction เพื่อลด peak memory
        for (let i = 0; i < gridSize; i++) {
            // ใช้ local Map สำหรับแต่ละ slice แล้วค่อย merge
            const sliceVoxels = new Map();
            
            for (let j = 0; j < gridSize; j++) {
                const absMax = Math.max(Math.abs(dir[0]), Math.abs(dir[1]), Math.abs(dir[2]));
                let ro;
                
                if (absMax === Math.abs(dir[0])) {
                    ro = [dir[0] > 0 ? -1 : gridSize, i, j];
                } else if (absMax === Math.abs(dir[1])) {
                    ro = [i, dir[1] > 0 ? -1 : gridSize, j];
                } else {
                    ro = [i, j, dir[2] > 0 ? -1 : gridSize];
                }
                
                const hits = bvh.intersectAll(ro, dir, scaledVertices, colors, uvs, textureIndices);
                hits.sort((a, b) => a.t - b.t);
                
                for (let h = 0; h < Math.min(3, hits.length); h++) {
                    const hit = hits[h];
                    const t = hit.t;
                    
                    const hitPos = [
                        ro[0] + t * dir[0],
                        ro[1] + t * dir[1],
                        ro[2] + t * dir[2]
                    ];
                    
                    const voxelPos = [
                        Math.floor(hitPos[0]),
                        Math.floor(hitPos[1]),
                        Math.floor(hitPos[2])
                    ];
                    
                    if (voxelPos[0] >= 0 && voxelPos[0] < gridSize &&
                        voxelPos[1] >= 0 && voxelPos[1] < gridSize &&
                        voxelPos[2] >= 0 && voxelPos[2] < gridSize) {
                        
                        const triIdx = hit.triIndex;
                        const cIdx = triIdx * 3;
                        let color = [colors[cIdx], colors[cIdx + 1], colors[cIdx + 2]];
                        
                        if (textureIndices[triIdx] !== -1 && extractor.textures[textureIndices[triIdx]]) {
                            const uvIdx = triIdx * 6;
                            const w = 1.0 - hit.u - hit.v;
                            const interpU = Math.max(0, Math.min(1, w * uvs[uvIdx] + hit.u * uvs[uvIdx + 2] + hit.v * uvs[uvIdx + 4]));
                            const interpV = Math.max(0, Math.min(1, w * uvs[uvIdx + 1] + hit.u * uvs[uvIdx + 3] + hit.v * uvs[uvIdx + 5]));
                            
                            const sampledColor = extractor.sampleTexture(textureIndices[triIdx], interpU, interpV);
                            if (sampledColor) {
                                color = sampledColor;
                            }
                        }
                        
                        // Pack position เป็น key เดียว (32-bit integer)
                        const key = (voxelPos[0] << 16) | (voxelPos[1] << 8) | voxelPos[2];
                        if (!sliceVoxels.has(key)) {
                            sliceVoxels.set(key, (color[0] << 16) | (color[1] << 8) | color[2]);
                        }
                    }
                }
            }
            
            // Merge slice voxels เข้า voxWriter
            for (const [key, packedColor] of sliceVoxels) {
                const x = (key >> 16) & 0xFF;
                const y = (key >> 8) & 0xFF;
                const z = key & 0xFF;
                const r = (packedColor >> 16) & 0xFF;
                const g = (packedColor >> 8) & 0xFF;
                const b = packedColor & 0xFF;
                
                const posKey = `${x},${y},${z}`;
                if (!voxWriter.voxels.has(posKey)) {
                    voxWriter.addVoxel(x, y, z, r, g, b);
                }
            }
        }
        
        const progress = 30 + Math.floor((rayCount / totalRays) * 50);
        progressCallback(progress);
    }
    
    console.log(`Found ${voxWriter.voxels.size} surface voxels`);
    
    // Center voxels
    let minVoxX = Infinity, minVoxY = Infinity, minVoxZ = Infinity;
    let maxVoxX = -Infinity, maxVoxY = -Infinity, maxVoxZ = -Infinity;
    
    for (const key of voxWriter.voxels.keys()) {
        const [x, y, z] = key.split(',').map(Number);
        minVoxX = Math.min(minVoxX, x);
        minVoxY = Math.min(minVoxY, y);
        minVoxZ = Math.min(minVoxZ, z);
        maxVoxX = Math.max(maxVoxX, x);
        maxVoxY = Math.max(maxVoxY, y);
        maxVoxZ = Math.max(maxVoxZ, z);
    }
    
    const centerOffsetX = Math.floor((gridSize - (maxVoxX - minVoxX + 1)) / 2) - minVoxX;
    const centerOffsetY = Math.floor((gridSize - (maxVoxY - minVoxY + 1)) / 2) - minVoxY;
    const centerOffsetZ = Math.floor((gridSize - (maxVoxZ - minVoxZ + 1)) / 2) - minVoxZ;
    
    // Re-center voxels
    if (centerOffsetX !== 0 || centerOffsetY !== 0 || centerOffsetZ !== 0) {
        const centeredVoxels = new Map();
        for (const [key, colorIdx] of voxWriter.voxels) {
            const [x, y, z] = key.split(',').map(Number);
            const newKey = `${x + centerOffsetX},${y + centerOffsetY},${z + centerOffsetZ}`;
            centeredVoxels.set(newKey, colorIdx);
        }
        voxWriter.voxels = centeredVoxels;
    }
    
    progressCallback(85);
    
    if (smoothRadius > 0) {
        console.log('Smoothing colors...');
        voxWriter.smoothColors(smoothRadius);
    }
    
    progressCallback(95);
    
    // Log memory usage estimate
    const bvhSize = triangleCount * 4; // indices only
    const voxelSize = voxWriter.voxels.size * 32; // approximate
    const totalMemory = (bvhSize + voxelSize) / 1024 / 1024;
    console.log(`Estimated memory usage: ${totalMemory.toFixed(2)}MB`);
    
    return { voxWriter, size: [gridSize, gridSize, gridSize] };
}

module.exports = {
    GLBExtractor,
    voxelizeMesh
};
