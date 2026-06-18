# Alderframe "site model": a stepped contour model of a ravine slope with three
# limestone terraces, a corten rill and abstract trees. Built headless:
#   blender -b -P site_model.py -- <out_dir>
# Exports site-model.glb (for three.js) and site-model.png (poster / no-JS fallback).
import bpy, bmesh, math, random, sys, os
from mathutils import Vector

out = sys.argv[sys.argv.index("--") + 1] if "--" in sys.argv else os.getcwd()
random.seed(7)
bpy.ops.wm.read_factory_settings(use_empty=True)
scene = bpy.context.scene

def mat(name, hex_, rough=0.85):
    m = bpy.data.materials.new(name)
    m.use_nodes = True
    h = hex_.lstrip("#")
    rgb = [int(h[i:i + 2], 16) / 255 for i in (0, 2, 4)]
    lin = [c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4 for c in rgb]
    bsdf = m.node_tree.nodes["Principled BSDF"]
    bsdf.inputs["Base Color"].default_value = (*lin, 1)
    bsdf.inputs["Roughness"].default_value = rough
    return m

M_CONTOUR = mat("Contour", "#E9E4DA")
def contour_lines(m, step):
    nt = m.node_tree
    geo = nt.nodes.new("ShaderNodeNewGeometry")
    sep = nt.nodes.new("ShaderNodeSeparateXYZ")
    div = nt.nodes.new("ShaderNodeMath"); div.operation = "DIVIDE"; div.inputs[1].default_value = step
    frc = nt.nodes.new("ShaderNodeMath"); frc.operation = "FRACT"
    lt = nt.nodes.new("ShaderNodeMath"); lt.operation = "LESS_THAN"; lt.inputs[1].default_value = 0.09
    mix = nt.nodes.new("ShaderNodeMix"); mix.data_type = "RGBA"
    mix.inputs[6].default_value = nt.nodes["Principled BSDF"].inputs["Base Color"].default_value[:]
    mix.inputs[7].default_value = (0.36, 0.33, 0.28, 1)
    nt.links.new(geo.outputs["Position"], sep.inputs[0])
    nt.links.new(sep.outputs["Z"], div.inputs[0])
    nt.links.new(div.outputs[0], frc.inputs[0])
    nt.links.new(frc.outputs[0], lt.inputs[0])
    nt.links.new(lt.outputs[0], mix.inputs[0])
    nt.links.new(mix.outputs[2], nt.nodes["Principled BSDF"].inputs["Base Color"])
M_BASE = mat("Base", "#2B2D29", 0.7)
M_STONE = mat("Limestone", "#FBF8F2", 0.9)
M_WALL = mat("Wall", "#B9AE98", 0.9)
M_RILL = mat("Corten", "#8A3B12", 0.55)
M_CANOPY = mat("Canopy", "#2F4A32", 0.9)
M_TRUNK = mat("Trunk", "#5C6257", 0.9)

W, D = 4.0, 3.0          # model footprint (x, y)
STEP = 0.055             # contour layer thickness
TERRACES = [             # (y0, y1, level)
    (-1.05, -0.55, 0.22),
    (-0.25, 0.20, 0.50),
    (0.48, 0.92, 0.80),
]
TX0, TX1 = -1.35, 0.45   # terrace x extent

def natural(x, y):
    t = max(0.0, min(1.0, (y + 1.5) / 3.0))
    return 0.04 + 1.05 * t ** 1.25 + 0.07 * math.sin(2 * x + 1) * math.cos(1.5 * y) + 0.05 * math.sin(3.1 * x - 0.7 * y)

def height(x, y):
    h = natural(x, y)
    for y0, y1, lv in TERRACES:
        # soft footprint so retaining edges read as steps
        dx = max(TX0 - x, 0, x - TX1)
        dy = max(y0 - y, 0, y - y1)
        d = math.hypot(dx, dy)
        if d < 0.07:
            w = 1 - d / 0.07
            h = h * (1 - w) + lv * w
    return h  # smooth; contour lines are drawn by the material (Blender) / shader (three.js)

# --- terrain: stepped grid + skirt down to z=0 ---------------------------------
bm = bmesh.new()
nx, ny = 160, 120
verts = {}
for j in range(ny + 1):
    for i in range(nx + 1):
        x = -W / 2 + W * i / nx
        y = -D / 2 + D * j / ny
        verts[i, j] = bm.verts.new((x, y, height(x, y)))
for j in range(ny):
    for i in range(nx):
        bm.faces.new((verts[i, j], verts[i + 1, j], verts[i + 1, j + 1], verts[i, j + 1]))
boundary = [e for e in bm.edges if e.is_boundary]
ext = bmesh.ops.extrude_edge_only(bm, edges=boundary)
for v in ext["geom"]:
    if isinstance(v, bmesh.types.BMVert):
        v.co.z = 0.0
me = bpy.data.meshes.new("Terrain")
bm.to_mesh(me)
bm.free()
for p in me.polygons:
    p.use_smooth = True
terrain = bpy.data.objects.new("Terrain", me)
terrain.data.materials.append(M_CONTOUR)
scene.collection.objects.link(terrain)

contour_lines(M_CONTOUR, STEP)

# --- base plinth ---------------------------------------------------------------
bpy.ops.mesh.primitive_cube_add(size=1, location=(0, 0, -0.09))
base = bpy.context.active_object
base.name = "Base"
base.scale = (W + 0.16, D + 0.16, 0.18)
base.data.materials.append(M_BASE)

# --- limestone terrace slabs -----------------------------------------------------
for n, (y0, y1, lv) in enumerate(TERRACES, 1):
    bpy.ops.mesh.primitive_cube_add(size=1)
    s = bpy.context.active_object
    s.name = f"Terrace_{n}"
    s.scale = (TX1 - TX0 - 0.1, y1 - y0 - 0.08, 0.04)
    s.location = ((TX0 + TX1) / 2, (y0 + y1) / 2, lv + 0.02)
    s.data.materials.append(M_STONE)
    # dry-stack retaining wall on the downhill edge
    low = natural((TX0 + TX1) / 2, y0 - 0.12)
    hgt = max(lv - low + 0.06, 0.08)
    bpy.ops.mesh.primitive_cube_add(size=1)
    w = bpy.context.active_object
    w.name = f"Wall_{n}"
    w.scale = (TX1 - TX0 + 0.04, 0.06, hgt)
    w.location = ((TX0 + TX1) / 2, y0 - 0.02, lv + 0.04 - hgt / 2)
    w.data.materials.append(M_WALL)

# --- corten rill dropping terrace to terrace -------------------------------------
top = Vector((0.62, 0.95, 1.02))
bot = Vector((0.62, -1.1, 0.30))
mid = (top + bot) / 2
bpy.ops.mesh.primitive_cube_add(size=1, location=mid)
rill = bpy.context.active_object
rill.name = "Rill"
length = (top - bot).length
rill.scale = (0.07, length, 0.03)
rill.rotation_euler.x = math.atan2(top.z - bot.z, top.y - bot.y)
rill.data.materials.append(M_RILL)

# --- abstract trees, origin at the root so they can grow from the ground ---------
def on_terrace(x, y):
    return TX0 - 0.15 < x < TX1 + 0.3 and any(y0 - 0.15 < y < y1 + 0.15 for y0, y1, _ in TERRACES)

placed, tries = [], 0
while len(placed) < 15 and tries < 2000:
    tries += 1
    x, y = random.uniform(-1.85, 1.85), random.uniform(-1.35, 1.35)
    if on_terrace(x, y) or abs(x - 0.62) < 0.2:
        continue
    if any((x - a) ** 2 + (y - b) ** 2 < 0.28 ** 2 for a, b in placed):
        continue
    placed.append((x, y))

for n, (x, y) in enumerate(placed):
    z = height(x, y)
    th = random.uniform(0.28, 0.5)
    r = random.uniform(0.12, 0.22)
    bpy.ops.mesh.primitive_cylinder_add(vertices=8, radius=0.018, depth=th, location=(x, y, z + th / 2))
    trunk = bpy.context.active_object
    trunk.data.materials.append(M_TRUNK)
    bpy.ops.mesh.primitive_ico_sphere_add(subdivisions=2, radius=r, location=(x, y, z + th + r * 0.6))
    crown = bpy.context.active_object
    crown.scale.z = random.uniform(0.85, 1.25)
    crown.data.materials.append(M_CANOPY)
    bpy.ops.object.select_all(action="DESELECT")
    trunk.select_set(True)
    crown.select_set(True)
    bpy.context.view_layer.objects.active = crown
    bpy.ops.object.join()
    tree = bpy.context.active_object
    tree.name = f"Tree_{n + 1:02d}"
    scene.cursor.location = (x, y, z)
    bpy.ops.object.origin_set(type="ORIGIN_CURSOR")
    for p in tree.data.polygons:
        p.use_smooth = False

# --- export ------------------------------------------------------------------------
bpy.ops.object.select_all(action="SELECT")
bpy.ops.export_scene.gltf(filepath=os.path.join(out, "site-model.glb"), export_format="GLB",
                          use_selection=True, export_apply=True, export_yup=True)

# --- poster render ---------------------------------------------------------------
cam_data = bpy.data.cameras.new("Cam")
cam_data.lens = 55
cam = bpy.data.objects.new("Cam", cam_data)
scene.collection.objects.link(cam)
cam.location = (5.4, -6.2, 4.6)
direction = Vector((0, 0.1, 0.35)) - cam.location
cam.rotation_euler = direction.to_track_quat("-Z", "Y").to_euler()
scene.camera = cam
sun = bpy.data.objects.new("Sun", bpy.data.lights.new("Sun", "SUN"))
sun.data.energy = 3.2
sun.data.angle = math.radians(8)
sun.rotation_euler = (math.radians(52), math.radians(18), math.radians(35))
scene.collection.objects.link(sun)
world = bpy.data.worlds.new("World")
world.use_nodes = True
world.node_tree.nodes["Background"].inputs[0].default_value = (0.93, 0.92, 0.88, 1)
world.node_tree.nodes["Background"].inputs[1].default_value = 0.9
scene.world = world
scene.render.engine = "BLENDER_EEVEE" if "BLENDER_EEVEE" in [e.identifier for e in bpy.types.RenderSettings.bl_rna.properties["engine"].enum_items] else "BLENDER_EEVEE_NEXT"
scene.render.film_transparent = True
scene.render.resolution_x, scene.render.resolution_y = 1600, 1200
scene.render.filepath = os.path.join(out, "site-model.png")
bpy.ops.render.render(write_still=True)
bpy.ops.wm.save_as_mainfile(filepath=os.path.join(out, "site-model.blend"))
print("AF_DONE", out)
