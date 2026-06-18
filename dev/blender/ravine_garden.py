# Alderframe signature scene: a photoreal terraced ravine garden (Rosedale-style slope),
# three dry-stack limestone terraces, flagstone paving, a corten rill with water,
# woodland trees, ferns and meadow grass, golden-hour sky. EEVEE (raytraced) for speed.
#
#   blender -b --factory-startup -P ravine_garden.py -- <out_dir> [still|seq] [WxH] [samples] [f0] [f1]
#     still -> poster frames (stills/frame_###.png)   seq -> scroll sequence (seq/f_###.webp)
import bpy, bmesh, math, random, sys, os
from mathutils import Vector, Matrix, noise

argv = sys.argv[sys.argv.index("--") + 1:] if "--" in sys.argv else []
OUT = argv[0] if argv else os.getcwd()
MODE = argv[1] if len(argv) > 1 else "still"
RES = tuple(int(v) for v in (argv[2] if len(argv) > 2 else "960x600").split("x"))
SAMPLES = int(argv[3]) if len(argv) > 3 else 16
F0 = int(argv[4]) if len(argv) > 4 else 1
F1 = int(argv[5]) if len(argv) > 5 else 72

random.seed(11)
bpy.ops.wm.read_factory_settings(use_empty=True)
scene = bpy.context.scene
col = scene.collection

# ----------------------------------------------------------------- materials
def srgb(h):
    h = h.lstrip("#")
    c = [int(h[i:i + 2], 16) / 255 for i in (0, 2, 4)]
    return [x / 12.92 if x <= 0.04045 else ((x + 0.055) / 1.055) ** 2.4 for x in c] + [1]

class M:
    """Tiny node-building helper."""
    def __init__(self, name):
        self.m = bpy.data.materials.new(name)
        self.m.use_nodes = True
        self.nt = self.m.node_tree
        self.bsdf = self.nt.nodes["Principled BSDF"]
    def n(self, t, **kw):
        node = self.nt.nodes.new(t)
        for k, v in kw.items():
            if k.startswith("in_"):
                node.inputs[k[3:].replace("_", " ")].default_value = v
            else:
                setattr(node, k, v)
        return node
    def l(self, a, b):
        self.nt.links.new(a, b)
    def ramp(self, fac, stops):
        r = self.n("ShaderNodeValToRGB")
        els = r.color_ramp.elements
        els[0].position, els[0].color = stops[0][0], srgb(stops[0][1])
        els[1].position, els[1].color = stops[-1][0], srgb(stops[-1][1])
        for p, c in stops[1:-1]:
            e = els.new(p); e.color = srgb(c)
        self.l(fac, r.inputs[0])
        return r
    def bump(self, height, strength=0.3, distance=0.05):
        b = self.n("ShaderNodeBump", in_Strength=strength, in_Distance=distance)
        self.l(height, b.inputs["Height"])
        self.l(b.outputs[0], self.bsdf.inputs["Normal"])

def mat_limestone():
    m = M("Limestone")
    tc = m.n("ShaderNodeNewGeometry")
    big = m.n("ShaderNodeTexNoise", in_Scale=1.6, in_Detail=8.0, in_Roughness=0.62)
    pit = m.n("ShaderNodeTexVoronoi", in_Scale=38.0)
    rnd = m.n("ShaderNodeObjectInfo")
    cell = m.n("ShaderNodeTexVoronoi", in_Scale=2.2)
    m.l(tc.outputs["Position"], big.inputs["Vector"]); m.l(tc.outputs["Position"], pit.inputs["Vector"]); m.l(tc.outputs["Position"], cell.inputs["Vector"])
    sep = m.n("ShaderNodeSeparateColor")
    m.l(cell.outputs["Color"], sep.inputs[0])
    mix = m.n("ShaderNodeMath", operation="MULTIPLY_ADD")
    m.l(big.outputs["Fac"], mix.inputs[0]); mix.inputs[1].default_value = 0.7
    m.l(sep.outputs[0], mix.inputs[2])
    col_ = m.ramp(mix.outputs[0], [(0.35, "#6E675A"), (0.6, "#9C927E"), (0.85, "#B8AE97"), (1.3, "#857A64")])
    m.l(col_.outputs[0], m.bsdf.inputs["Base Color"])
    m.bsdf.inputs["Roughness"].default_value = 0.88
    h = m.n("ShaderNodeMath", operation="ADD")
    m.l(big.outputs["Fac"], h.inputs[0]); m.l(pit.outputs["Distance"], h.inputs[1])
    m.bump(h.outputs[0], 0.45, 0.04)
    return m.m

def mat_flagstone():
    m = M("Flagstone")
    tc = m.n("ShaderNodeNewGeometry")
    vor = m.n("ShaderNodeTexVoronoi", feature="DISTANCE_TO_EDGE", in_Scale=1.5)
    nz = m.n("ShaderNodeTexNoise", in_Scale=4.0, in_Detail=10.0)
    cell = m.n("ShaderNodeTexVoronoi", in_Scale=1.5)
    m.l(tc.outputs["Position"], vor.inputs["Vector"]); m.l(tc.outputs["Position"], nz.inputs["Vector"]); m.l(tc.outputs["Position"], cell.inputs["Vector"])
    joint = m.n("ShaderNodeMapRange", in_From_Min=0.0, in_From_Max=0.035)
    m.l(vor.outputs["Distance"], joint.inputs["Value"])
    tint = m.n("ShaderNodeMath", operation="MULTIPLY_ADD")
    m.l(cell.outputs["Distance"], tint.inputs[0]); tint.inputs[1].default_value = 0.5; m.l(nz.outputs["Fac"], tint.inputs[2])
    stone = m.ramp(tint.outputs[0], [(0.35, "#6F6A5E"), (0.6, "#8F8878"), (0.85, "#A9A08D"), (1.1, "#7E7766")])
    mixc = m.n("ShaderNodeMix", data_type="RGBA")
    m.l(joint.outputs[0], mixc.inputs["Factor"])
    mixc.inputs[6].default_value = srgb("#3E3A2E")
    m.l(stone.outputs[0], mixc.inputs[7])
    m.l(mixc.outputs[2], m.bsdf.inputs["Base Color"])
    m.bsdf.inputs["Roughness"].default_value = 0.8
    h = m.n("ShaderNodeMath", operation="MULTIPLY_ADD")
    m.l(joint.outputs[0], h.inputs[0]); h.inputs[1].default_value = 0.6; m.l(nz.outputs["Fac"], h.inputs[2])
    m.bump(h.outputs[0], 0.5, 0.03)
    return m.m

def mat_ground():
    m = M("ForestFloor")
    tc = m.n("ShaderNodeTexCoord")
    patch = m.n("ShaderNodeTexNoise", in_Scale=0.18, in_Detail=6.0)
    fine = m.n("ShaderNodeTexNoise", in_Scale=6.0, in_Detail=12.0, in_Roughness=0.7)
    m.l(tc.outputs["Object"], patch.inputs["Vector"]); m.l(tc.outputs["Object"], fine.inputs["Vector"])
    f = m.n("ShaderNodeMath", operation="MULTIPLY_ADD")
    m.l(patch.outputs["Fac"], f.inputs[0]); f.inputs[1].default_value = 1.0
    fd = m.n("ShaderNodeMath", operation="MULTIPLY", in_Value=0.35)
    m.l(fine.outputs["Fac"], fd.inputs[0]); fd.inputs[1].default_value = 0.35
    m.l(fd.outputs[0], f.inputs[2])
    c = m.ramp(f.outputs[0], [(0.35, "#3E3322"), (0.5, "#4D4A2A"), (0.65, "#3F5226"), (0.9, "#34491F")])
    m.l(c.outputs[0], m.bsdf.inputs["Base Color"])
    m.bsdf.inputs["Roughness"].default_value = 0.95
    m.bump(fine.outputs["Fac"], 0.6, 0.05)
    return m.m

def mat_corten():
    m = M("Corten")
    tc = m.n("ShaderNodeTexCoord")
    nz = m.n("ShaderNodeTexNoise", in_Scale=14.0, in_Detail=12.0, in_Roughness=0.7)
    m.l(tc.outputs["Object"], nz.inputs["Vector"])
    c = m.ramp(nz.outputs["Fac"], [(0.3, "#4A1E0B"), (0.5, "#8A3B12"), (0.7, "#A9541E"), (1.0, "#6B2D10")])
    m.l(c.outputs[0], m.bsdf.inputs["Base Color"])
    m.bsdf.inputs["Roughness"].default_value = 0.72
    m.bsdf.inputs["Metallic"].default_value = 0.35
    m.bump(nz.outputs["Fac"], 0.35, 0.01)
    return m.m

def mat_water():
    m = M("Water")
    m.bsdf.inputs["Base Color"].default_value = srgb("#1A2620")
    m.bsdf.inputs["Roughness"].default_value = 0.06
    m.bsdf.inputs["Transmission Weight"].default_value = 0.0
    m.bsdf.inputs["Specular IOR Level"].default_value = 0.8
    m.bsdf.inputs["IOR"].default_value = 1.33
    tc = m.n("ShaderNodeTexCoord")
    w = m.n("ShaderNodeTexWave", in_Scale=3.0, in_Distortion=6.0, in_Detail=4.0)
    m.l(tc.outputs["Object"], w.inputs["Vector"])
    m.bump(w.outputs["Fac"], 0.12, 0.01)
    return m.m

def mat_wood():
    m = M("WeatheredOak")
    tc = m.n("ShaderNodeTexCoord")
    w = m.n("ShaderNodeTexWave", wave_type="BANDS", bands_direction="X", in_Scale=2.0, in_Distortion=12.0, in_Detail=6.0)
    m.l(tc.outputs["Object"], w.inputs["Vector"])
    c = m.ramp(w.outputs["Fac"], [(0.2, "#6D6352"), (0.6, "#8F8470"), (1.0, "#A79B84")])
    m.l(c.outputs[0], m.bsdf.inputs["Base Color"])
    m.bsdf.inputs["Roughness"].default_value = 0.85
    m.bump(w.outputs["Fac"], 0.3, 0.01)
    return m.m

def mat_bark():
    m = M("Bark")
    tc = m.n("ShaderNodeTexCoord")
    mp = m.n("ShaderNodeMapping"); mp.inputs["Scale"].default_value = (6, 6, 0.8)
    m.l(tc.outputs["Object"], mp.inputs["Vector"])
    nz = m.n("ShaderNodeTexNoise", in_Scale=4.0, in_Detail=12.0, in_Roughness=0.75)
    m.l(mp.outputs[0], nz.inputs["Vector"])
    c = m.ramp(nz.outputs["Fac"], [(0.3, "#3F3A33"), (0.6, "#6B655B"), (1.0, "#8E877B")])
    m.l(c.outputs[0], m.bsdf.inputs["Base Color"])
    m.bsdf.inputs["Roughness"].default_value = 0.93
    m.bump(nz.outputs["Fac"], 0.8, 0.03)
    return m.m

def mat_foliage(name, a, b, translucency=0.25):
    m = M(name)
    oi = m.n("ShaderNodeObjectInfo")
    c = m.ramp(oi.outputs["Random"], [(0.0, a), (1.0, b)])
    m.l(c.outputs[0], m.bsdf.inputs["Base Color"])
    m.bsdf.inputs["Roughness"].default_value = 0.6
    m.bsdf.inputs["Subsurface Weight"].default_value = translucency
    m.bsdf.inputs["Subsurface Radius"].default_value = (0.2, 0.4, 0.1)
    m.m.use_backface_culling = False
    return m.m

MAT = dict(stone=mat_limestone(), flag=mat_flagstone(), ground=mat_ground(), corten=mat_corten(),
           water=mat_water(), wood=mat_wood(), bark=mat_bark(),
           leaf=mat_foliage("Leaf", "#3E5A26", "#7A8F38"), grass=mat_foliage("Grass", "#3F5A22", "#7C8C3A", 0.3),
           fern=mat_foliage("Fern", "#2F4F22", "#5D7A2E", 0.35))

def obj(name, me, mat=None):
    o = bpy.data.objects.new(name, me)
    col.objects.link(o)
    if mat:
        o.data.materials.append(mat)
    return o

# ----------------------------------------------------------------- terrain
W, D = 46.0, 36.0
GW, GD = 200.0, 180.0  # ground extent (site sits at centre)
TERR = [(-10.5, -6.5, 1.5), (-3.0, 1.0, 4.1), (4.5, 8.5, 6.9)]   # (y0, y1, level)
TX0, TX1 = -13.0, 4.0
RX = 6.2   # rill x

def natural(x, y):
    t = max(0.0, min(1.25, (y + 18) / 36))
    h = 0.2 + 9.5 * t ** 1.3 + 0.55 * math.sin(0.2 * x + 1) * math.cos(0.15 * y) + 0.35 * math.sin(0.31 * x - 0.07 * y)
    return h + 0.35 * noise.fractal(Vector((x * 0.25, y * 0.25, 0)), 0.8, 2.0, 4)

def height(x, y):
    h = natural(x, y)
    for y0, y1, lv in TERR:
        dx = max(TX0 - x, 0, x - TX1)
        if y < y0:                       # in front of the wall: drop away so the stone face shows
            dy = y0 - y
            if dx < 1.2 and dy < 1.6:
                h -= 0.45 * (1 - dy / 1.6) * (1 - dx / 1.2)
            continue
        dy = max(y - y1, 0)
        d = math.hypot(dx, dy)
        if d < 1.4:
            w = (1 - d / 1.4) ** 0.5
            h = h * (1 - w) + (lv - 0.05) * w
    if abs(x - RX) < 0.9:
        h -= 0.25 * (1 - abs(x - RX) / 0.9)
    return h

def masks(x, y):
    on_t = TX0 - 0.6 < x < TX1 + 0.6 and any(y0 - 0.9 < y < y1 + 0.3 for y0, y1, _ in TERR)
    near_wall = TX0 - 0.5 < x < TX1 + 0.5 and any(abs(y - (y0 - 1.1)) < 0.9 for y0, _, _ in TERR)
    rill = abs(x - RX) < 1.0
    g = 0.0 if (on_t or rill) else 1.0
    f = 1.0 if (near_wall or (0.8 < abs(x - RX) < 2.2)) and not on_t else 0.0
    return g, f

bm = bmesh.new()
def axis(lo, hi, near_lo, near_hi, fine, coarse):
    vals, v = [], lo
    while v < hi:
        vals.append(v)
        v += fine if near_lo - 2 < v < near_hi + 2 else coarse
    return vals + [hi]
xs = axis(-GW / 2, GW / 2, -W / 2, W / 2, 0.22, 1.5)
ys = axis(-GD / 2 + 10, GD / 2 + 10, -D / 2, D / 2, 0.22, 1.5)
nx, ny = len(xs) - 1, len(ys) - 1
grid = {}
for j, y in enumerate(ys):
    for i, x in enumerate(xs):
        grid[i, j] = bm.verts.new((x, y, height(x, y)))
for j in range(ny):
    for i in range(nx):
        bm.faces.new((grid[i, j], grid[i + 1, j], grid[i + 1, j + 1], grid[i, j + 1]))
me = bpy.data.meshes.new("Terrain")
bm.to_mesh(me); bm.free()
for p in me.polygons:
    p.use_smooth = True
ga = me.attributes.new("grass", "FLOAT", "POINT")
fa = me.attributes.new("fern", "FLOAT", "POINT")
for v in me.vertices:
    g, f = masks(v.co.x, v.co.y)
    far = max(abs(v.co.x) - W / 2, abs(v.co.y) - D / 2, 0)
    g *= max(0.0, 1 - far / 14)
    f *= 0 if far > 0 else 1
    ga.data[v.index].value, fa.data[v.index].value = g, f
terrain = obj("Terrain", me, MAT["ground"])

# ----------------------------------------------------------------- dry-stack walls + paving
def add_block(bm, center, size, rot_z=0.0, bevel=0.035):
    r = bmesh.ops.create_cube(bm, size=1.0, matrix=Matrix.Translation(center) @ Matrix.Rotation(rot_z, 4, "Z") @ Matrix.Diagonal((*size, 1)))
    verts = r["verts"]
    edges = list({e for v in verts for e in v.link_edges})
    bmesh.ops.bevel(bm, geom=verts + edges, offset=bevel, segments=2, affect="EDGES", profile=0.6)

for n, (y0, y1, lv) in enumerate(TERR, 1):
    bm = bmesh.new()
    base = natural((TX0 + TX1) / 2, y0 - 1.0) - 0.4
    z = base
    while z < lv - 0.05:
        ch = random.uniform(0.17, 0.3)
        x = TX0 - 0.3 + random.uniform(0, 0.4)
        while x < TX1 + 0.3:
            ln = random.uniform(0.45, 1.0)
            dep = random.uniform(0.42, 0.55)
            sh = ch * random.uniform(0.82, 1.0)
            add_block(bm, Vector((x + ln / 2, y0 - 0.25 + random.uniform(-0.07, 0.05), z + sh / 2)),
                      (ln - random.uniform(0.02, 0.05), dep, sh - 0.02), random.uniform(-0.06, 0.06), random.uniform(0.03, 0.06))
            x += ln
        z += ch
    # cap stones
    x = TX0 - 0.4
    while x < TX1 + 0.4:
        ln = random.uniform(0.7, 1.3)
        add_block(bm, Vector((x + ln / 2, y0 - 0.22, lv + 0.06)), (ln - 0.03, 0.7, 0.14), random.uniform(-0.02, 0.02), 0.05)
        x += ln
    me = bpy.data.meshes.new(f"Wall_{n}")
    bm.to_mesh(me); bm.free()
    obj(f"Wall_{n}", me, MAT["stone"])
    # flagstone terrace
    bpy.ops.mesh.primitive_plane_add(size=1, location=((TX0 + TX1) / 2, (y0 + y1) / 2 + 0.2, lv + 0.11))
    pave = bpy.context.active_object
    pave.name = f"Terrace_{n}"
    pave.scale = (TX1 - TX0 + 0.4, y1 - y0 - 0.6, 1)
    pave.data.materials.append(MAT["flag"])
    sol = pave.modifiers.new("t", "SOLIDIFY"); sol.thickness = 0.12

# ----------------------------------------------------------------- corten rill + water
def rill_path():
    pts = []
    for k in range(91):
        y = 10.5 - k * (21.5 / 90)
        pts.append(Vector((RX, y, height(RX, y) + 0.32)))
    return pts

bm = bmesh.new(); wb = bmesh.new()
prev = prevw = None
for p in rill_path():
    prof = [bm.verts.new(p + Vector(o)) for o in ((-0.32, 0, 0.05), (-0.28, 0, -0.24), (0.28, 0, -0.24), (0.32, 0, 0.05))]
    wv = [wb.verts.new(p + Vector((dx, 0, -0.1))) for dx in (-0.27, 0.27)]
    if prev:
        for a in range(3):
            bm.faces.new((prev[a], prev[a + 1], prof[a + 1], prof[a]))
        wb.faces.new((prevw[0], prevw[1], wv[1], wv[0]))
    prev, prevw = prof, wv
me = bpy.data.meshes.new("Rill"); bm.to_mesh(me); bm.free()
r = obj("Rill", me, MAT["corten"])
r.modifiers.new("t", "SOLIDIFY").thickness = 0.025
me = bpy.data.meshes.new("Water"); wb.to_mesh(me); wb.free()
obj("Water", me, MAT["water"])

# ----------------------------------------------------------------- bench on the top terrace
bm = bmesh.new()
lv = TERR[2][2] + 0.17
add_block(bm, Vector((-3.0, 6.6, lv + 0.45)), (2.4, 0.5, 0.1), 0.0, 0.015)
for dx in (-1.0, 1.0):
    add_block(bm, Vector((-3.0 + dx, 6.6, lv + 0.2)), (0.12, 0.45, 0.42), 0.0, 0.01)
me = bpy.data.meshes.new("Bench"); bm.to_mesh(me); bm.free()
obj("Bench", me, MAT["wood"])

# ----------------------------------------------------------------- vegetation sources (hidden)
src_col = bpy.data.collections.new("Sources")
scene.collection.children.link(src_col)
bpy.context.view_layer.layer_collection.children["Sources"].exclude = True

def src(name, bm, mat):
    me = bpy.data.meshes.new(name); bm.to_mesh(me); bm.free()
    o = bpy.data.objects.new(name, me); o.data.materials.append(mat)
    src_col.objects.link(o)
    return o

def blade(bm, base, h, w, lean, yaw, segs=4):
    prev = None
    for s in range(segs + 1):
        t = s / segs
        off = Vector((math.cos(yaw), math.sin(yaw), 0)) * lean * t * t
        c = base + off + Vector((0, 0, h * t))
        half = w * (1 - t) * 0.5 + 0.0008
        side = Vector((-math.sin(yaw), math.cos(yaw), 0)) * half
        cur = (bm.verts.new(c - side), bm.verts.new(c + side))
        if prev:
            bm.faces.new((prev[0], prev[1], cur[1], cur[0]))
        prev = cur

bm = bmesh.new()
for _ in range(9):
    a = random.uniform(0, math.tau)
    blade(bm, Vector((math.cos(a) * 0.04, math.sin(a) * 0.04, 0)), random.uniform(0.12, 0.32), 0.01, random.uniform(0.03, 0.12), a, 3)
GRASS = src("GrassClump", bm, MAT["grass"])

bm = bmesh.new()
for k in range(9):
    yaw = k / 9 * math.tau + random.uniform(-0.2, 0.2)
    pitch = random.uniform(0.5, 0.9)
    L = random.uniform(0.6, 0.95)
    d = Vector((math.cos(yaw), math.sin(yaw), 0))
    side = Vector((-d.y, d.x, 0))
    steps = 16
    for s in range(1, steps):
        t = s / steps
        p = d * (L * t * math.cos(pitch)) + Vector((0, 0, L * t * math.sin(pitch) - 0.45 * L * t * t))
        size = 0.13 * (1 - t) + 0.02
        for sgn in (-1, 1):
            tip = p + side * sgn * size + d * 0.02 - Vector((0, 0, 0.02))
            bm.faces.new((bm.verts.new(p - d * 0.018), bm.verts.new(tip), bm.verts.new(p + d * 0.018)))
FERN = src("Fern", bm, MAT["fern"])

bm = bmesh.new()
pts = [(0, 0), (0.05, 0.045), (0.058, 0.1), (0.0, 0.16), (-0.058, 0.1), (-0.05, 0.045)]
vs = [bm.verts.new((x, y, 0.004 * math.sin(y * 30))) for x, y in pts]
bm.faces.new(vs)
LEAF = src("Leaf", bm, MAT["leaf"])

# ----------------------------------------------------------------- geometry-nodes scatter
def scatter(target, source, density, attr=None, smin=0.7, smax=1.3, seed=0, keep=True, align_normal=False):
    ng = bpy.data.node_groups.new(f"Scatter_{source.name}_{target.name}", "GeometryNodeTree")
    ng.interface.new_socket("Geometry", in_out="INPUT", socket_type="NodeSocketGeometry")
    ng.interface.new_socket("Geometry", in_out="OUTPUT", socket_type="NodeSocketGeometry")
    N, L = ng.nodes.new, ng.links.new
    gi, go = N("NodeGroupInput"), N("NodeGroupOutput")
    dist = N("GeometryNodeDistributePointsOnFaces"); dist.inputs["Seed"].default_value = seed
    if attr:
        na = N("GeometryNodeInputNamedAttribute"); na.inputs["Name"].default_value = attr
        mul = N("ShaderNodeMath"); mul.operation = "MULTIPLY"; mul.inputs[1].default_value = density
        L(na.outputs["Attribute"], mul.inputs[0]); L(mul.outputs[0], dist.inputs["Density"])
    else:
        dist.inputs["Density"].default_value = density
    L(gi.outputs[0], dist.inputs["Mesh"])
    info = N("GeometryNodeObjectInfo"); info.inputs["Object"].default_value = source; info.inputs["As Instance"].default_value = True
    inst = N("GeometryNodeInstanceOnPoints")
    L(dist.outputs["Points"], inst.inputs["Points"]); L(info.outputs["Geometry"], inst.inputs["Instance"])
    yaw = N("FunctionNodeRandomValue"); yaw.inputs["Min"].default_value = 0; yaw.inputs["Max"].default_value = math.tau; yaw.inputs["Seed"].default_value = seed + 1
    tilt = N("FunctionNodeRandomValue"); tilt.inputs["Min"].default_value = -0.15; tilt.inputs["Max"].default_value = 0.15; tilt.inputs["Seed"].default_value = seed + 2
    comb = N("ShaderNodeCombineXYZ")
    L(tilt.outputs[0], comb.inputs[0]); L(yaw.outputs[0], comb.inputs[2])
    if align_normal:
        al = N("FunctionNodeAlignEulerToVector"); al.axis = "Z"
        L(comb.outputs[0], al.inputs["Rotation"]); L(dist.outputs["Normal"], al.inputs["Vector"])
        L(al.outputs[0], inst.inputs["Rotation"])
    else:
        L(comb.outputs[0], inst.inputs["Rotation"])
    sc = N("FunctionNodeRandomValue"); sc.inputs["Min"].default_value = smin; sc.inputs["Max"].default_value = smax; sc.inputs["Seed"].default_value = seed + 3
    L(sc.outputs[0], inst.inputs["Scale"])
    if keep:
        j = N("GeometryNodeJoinGeometry")
        L(inst.outputs[0], j.inputs[0]); L(gi.outputs[0], j.inputs[0]); L(j.outputs[0], go.inputs[0])
    else:
        L(inst.outputs[0], go.inputs[0])
    target.modifiers.new("Scatter " + source.name, "NODES").node_group = ng

scatter(terrain, GRASS, 70.0, "grass", 0.6, 1.4, 3)
scatter(terrain, FERN, 2.2, "fern", 0.7, 1.6, 9)

# ----------------------------------------------------------------- trees
def tree(x, y, hgt, seed, z0=None, into=None):
    rnd = random.Random(seed)
    z0 = (height(x, y) - 0.2) if z0 is None else z0
    into = into or col
    bm = bmesh.new()
    def limb(start, direction, length, r0, r1, rings=10):
        prev = None
        side = direction.orthogonal().normalized()
        bend = Vector((rnd.uniform(-1, 1), rnd.uniform(-1, 1), 0)) * length * 0.05
        for k in range(rings + 1):
            t = k / rings
            c = start + direction * length * t + bend * math.sin(t * math.pi * 0.8) + Vector((rnd.uniform(-1, 1), rnd.uniform(-1, 1), 0)) * 0.04 * t
            rad = r0 + (r1 - r0) * t
            ring = []
            for s in range(10):
                a = s / 10 * math.tau
                off = (side * math.cos(a) + direction.cross(side).normalized() * math.sin(a)) * rad * (1 + 0.08 * math.sin(a * 3 + k))
                ring.append(bm.verts.new(c + off))
            if prev:
                for s in range(10):
                    bm.faces.new((prev[s], prev[(s + 1) % 10], ring[(s + 1) % 10], ring[s]))
            prev = ring
        return start + direction * length
    trunk_dir = Vector((rnd.uniform(-0.08, 0.08), rnd.uniform(-0.08, 0.08), 1)).normalized()
    top = limb(Vector((x, y, z0)), trunk_dir, hgt, 0.26, 0.08, 14)
    crowns = [(top, 1.6)]
    for b in range(7):
        t = rnd.uniform(0.42, 0.85)
        st = Vector((x, y, z0)) + trunk_dir * hgt * t
        a = rnd.uniform(0, math.tau)
        d = Vector((math.cos(a), math.sin(a), rnd.uniform(0.35, 1.0))).normalized()
        L = hgt * rnd.uniform(0.25, 0.42)
        end = limb(st, d, L, 0.1, 0.025, 6)
        crowns += [(st + d * L * 0.55, rnd.uniform(0.8, 1.2)), (end, rnd.uniform(1.0, 1.5))]
    me = bpy.data.meshes.new(f"Tree_{seed}")
    bm.to_mesh(me); bm.free()
    for p in me.polygons:
        p.use_smooth = True
    t_o = bpy.data.objects.new(f"Tree_{seed}", me); t_o.data.materials.append(MAT["bark"]); into.objects.link(t_o)
    # canopy shells: invisible spheres carrying leaf instances
    bm = bmesh.new()
    for c, r in crowns:
        bmesh.ops.create_icosphere(bm, subdivisions=2, radius=r, matrix=Matrix.Translation(c + Vector((0, 0, r * 0.25))) @ Matrix.Diagonal((1.15, 1.15, 0.7, 1)))
    for v in bm.verts:   # lumpy, broken silhouette
        v.co += v.co.normalized() * 0 + Vector((noise.noise(v.co * 0.9), noise.noise(v.co * 0.9 + Vector((5, 1, 2))), noise.noise(v.co * 0.9 + Vector((9, 3, 7))))) * 0.55
    me = bpy.data.meshes.new(f"Canopy_{seed}")
    bm.to_mesh(me); bm.free()
    can = bpy.data.objects.new(f"Canopy_{seed}", me); can.data.materials.append(MAT["leaf"]); into.objects.link(can)
    scatter(can, LEAF, 55.0, None, 0.7, 1.8, seed, keep=False, align_normal=True)

spots, tries = [], 0
while len(spots) < 24 and tries < 6000:
    tries += 1
    x, y = random.uniform(-22, 22), random.uniform(-17, 17.5)
    if TX0 - 3 < x < TX1 + 3 and -12 < y < 10.5:
        continue
    if abs(x - RX) < 3 or (y < -11 and x > -6) or (0 < x < 23 and -18 < y < -3):
        continue
    if any((x - a) ** 2 + (y - b) ** 2 < 4.2 ** 2 for a, b in spots):
        continue
    spots.append((x, y))
for i, (x, y) in enumerate(spots):
    tree(x, y, random.uniform(7, 11), 100 + i)

protos = []
for k in range(5):
    pc = bpy.data.collections.new(f"TreeProto_{k}")
    src_col.children.link(pc)
    tree(0, 0, random.uniform(8, 13), 900 + k, z0=-0.2, into=pc)
    protos.append(pc)
placed = 0
for _ in range(9000):
    if placed >= 260:
        break
    x, y = random.uniform(-90, 90), random.uniform(-70, 95)
    if -W / 2 + 1 < x < W / 2 - 1 and -D / 2 + 1 < y < D / 2 - 1:
        continue
    if ( y < -24 and abs(x) < 40 ) or ( y < -14 and -8 < x < 42 ):      # keep the camera corridor open
        continue
    e = bpy.data.objects.new(f"Wood_{placed}", None)
    e.instance_type = "COLLECTION"
    e.instance_collection = random.choice(protos)
    e.location = (x, y, height(x, y))
    e.rotation_euler.z = random.uniform(0, math.tau)
    k_ = random.uniform(0.8, 1.25)
    e.scale = (k_, k_, k_ * random.uniform(0.9, 1.1))
    col.objects.link(e)
    placed += 1

# ----------------------------------------------------------------- sky, sun, haze
world = bpy.data.worlds.new("Sky"); world.use_nodes = True; scene.world = world
wn = world.node_tree.nodes
sky = wn.new("ShaderNodeTexSky")
sky.sky_type = "MULTIPLE_SCATTERING" if "MULTIPLE_SCATTERING" in [e.identifier for e in sky.bl_rna.properties["sky_type"].enum_items] else "NISHITA"
sky.sun_disc = False
sky.sun_elevation = math.radians(17)
sky.sun_rotation = math.radians(205)
world.node_tree.links.new(sky.outputs[0], wn["Background"].inputs[0])
wn["Background"].inputs[1].default_value = 0.22

sun = bpy.data.objects.new("Sun", bpy.data.lights.new("Sun", "SUN"))
sun.data.energy = 6.0
sun.data.angle = math.radians(1.2)
sun.data.color = (1.0, 0.82, 0.62)
el, az = math.radians(17), math.radians(205)
sun.rotation_euler = (math.pi / 2 - el, 0, az + math.pi / 2)
col.objects.link(sun)

# ----------------------------------------------------------------- camera move (scroll story)
target = bpy.data.objects.new("Target", None); col.objects.link(target)
cam = bpy.data.objects.new("Camera", bpy.data.cameras.new("Camera")); col.objects.link(cam)
scene.camera = cam
tc = cam.constraints.new("TRACK_TO"); tc.target = target; tc.track_axis = "TRACK_NEGATIVE_Z"; tc.up_axis = "UP_Y"
cam.data.dof.use_dof = True
cam.data.clip_end = 400
cam.data.dof.focus_object = target
cam.data.dof.aperture_fstop = 4.0
keys = [  # frame, camera, target, lens
    (1, (36, -40, 26), (-3, 1, 3.5), 34),
    (24, (27, -32, 17), (-3.5, 0, 3.6), 32),
    (48, (14, -21, 6.5), (-4, 0.5, 4.0), 28),
    (72, (2.4, -14.2, 2.25), (-2.0, 2.0, 3.6), 21),
]
for f, c, t, lens in keys:
    cam.location = c; target.location = t; cam.data.lens = lens
    cam.keyframe_insert("location", frame=f); target.keyframe_insert("location", frame=f); cam.data.keyframe_insert("lens", frame=f)
scene.frame_start, scene.frame_end = 1, 72

# ----------------------------------------------------------------- render settings
r = scene.render
r.engine = "BLENDER_EEVEE"
r.resolution_x, r.resolution_y = RES
r.resolution_percentage = 100
ee = scene.eevee
ee.taa_render_samples = SAMPLES
ee.use_raytracing = True
ee.use_shadows = True
ee.volumetric_end = 140
try:
    ee.fast_gi_method = "GLOBAL_ILLUMINATION"
    ee.use_fast_gi = True
except Exception:
    pass
for vt in ("AgX", "Filmic"):
    try:
        scene.view_settings.view_transform = vt
        break
    except Exception:
        pass
for look in ("AgX - Medium High Contrast", "Medium High Contrast"):
    try:
        scene.view_settings.look = look
        break
    except Exception:
        pass
scene.view_settings.exposure = 0.0

bpy.ops.wm.save_as_mainfile(filepath=os.path.join(OUT, "ravine-garden.blend"))

if MODE == "seq":
    os.makedirs(os.path.join(OUT, "seq"), exist_ok=True)
    r.image_settings.file_format = "WEBP"
    r.image_settings.quality = 72
    for f in range(F0, F1 + 1):
        scene.frame_set(f)
        r.filepath = os.path.join(OUT, "seq", f"f_{f:03d}.webp")
        bpy.ops.render.render(write_still=True)
        print("AF_FRAME", f, flush=True)
else:
    os.makedirs(os.path.join(OUT, "stills"), exist_ok=True)
    r.image_settings.file_format = "PNG"
    for f in (F0, F1) if F0 != F1 else (F0,):
        scene.frame_set(f)
        r.filepath = os.path.join(OUT, "stills", f"frame_{f:03d}.png")
        bpy.ops.render.render(write_still=True)
        print("AF_FRAME", f, flush=True)
print("AF_DONE")
