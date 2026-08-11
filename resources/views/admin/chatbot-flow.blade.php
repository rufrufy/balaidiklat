<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Flow Builder - Chatbot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=Ubuntu+Mono:wght@400;700&family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary:#072C2C;--secondary:#FF5F03;--surface-2:#F8F6EF;--text:#111827;--muted:#667085;--border:rgba(7,44,44,.16);--font-display:"Oswald",system-ui,sans-serif;--font-body:"Ubuntu",system-ui,sans-serif;--font-mono:"Ubuntu Mono",ui-monospace,monospace }
        * { box-sizing:border-box }
        body { margin:0;height:100vh;font-family:var(--font-body);color:var(--text);background:var(--surface-2);overflow:hidden }
        .btn-primary-enterprise { background:var(--primary);color:#fff;border:none;border-radius:999px;font-weight:800;padding:8px 18px;font-size:.85rem;cursor:pointer }
        .btn-primary-enterprise:hover { background:#0B3A3A }
        .btn-ghost { border:1px solid var(--border);background:#fff;color:var(--primary);border-radius:999px;font-weight:700;padding:8px 14px;font-size:.82rem;cursor:pointer }
        .btn-ghost:hover { background:var(--surface-2) }
        .btn-sm { padding:4px 10px;font-size:.78rem }
        .badge-soft { display:inline-flex;border-radius:999px;padding:.3rem .6rem;font-size:.75rem;font-weight:800 }
        .badge-primary-soft { background:rgba(7,44,44,.1);color:var(--primary) }
        .mono { font-family:var(--font-mono) }

        .app-layout { display:grid;grid-template-columns:1fr 360px;height:100vh }
        .canvas-wrap { position:relative;overflow:hidden;cursor:grab }
        .canvas-wrap:active { cursor:grabbing }
        .canvas-wrap.panning { cursor:grabbing }
        #svgLayer { position:absolute;top:0;left:0;pointer-events:none;z-index:1 }
        #svgLayer line { stroke:#94a3b8;stroke-width:2;stroke-opacity:.6 }
        #svgLayer line.highlight { stroke:var(--secondary);stroke-width:3;stroke-opacity:1 }
        #svgLayer text { font-family:var(--font-mono);font-size:11px;fill:var(--muted) }

        .flow-node { position:absolute;min-width:140px;max-width:180px;padding:14px 16px;border-radius:14px;color:#fff;cursor:grab;user-select:none;z-index:2;box-shadow:0 4px 12px rgba(0,0,0,.15);transition:transform .1s,box-shadow .15s;font-family:var(--font-body) }
        .flow-node:hover { box-shadow:0 6px 20px rgba(0,0,0,.2) }
        .flow-node:active { cursor:grabbing }
        .flow-node.selected { outline:3px solid var(--secondary);outline-offset:2px;z-index:5 }
        .flow-node.entry { outline:3px dashed #FF5F03;outline-offset:2px }
        .flow-node .node-label { font-weight:700;font-size:.88rem;line-height:1.2;word-break:break-word }
        .flow-node .node-key { font-size:.7rem;opacity:.7;margin-top:3px;font-family:var(--font-mono) }
        .flow-node .node-count { font-size:.7rem;opacity:.65;margin-top:4px }
        .flow-node .node-actions { display:none;margin-top:8px;gap:4px }
        .flow-node:hover .node-actions { display:flex }

        .panel { background:#fff;border-left:1px solid var(--border);overflow-y:auto;display:flex;flex-direction:column }
        .panel-header { border-bottom:1px solid var(--border);padding:14px 16px;display:flex;justify-content:space-between;align-items:center }
        .panel-body { padding:16px;flex:1;overflow-y:auto }
        .panel-empty { color:var(--muted);text-align:center;padding:40px 16px;font-size:.88rem }

        .toolbar { position:absolute;top:12px;left:12px;z-index:10;display:flex;gap:6px;flex-wrap:wrap }
        .toolbar .btn { backdrop-filter:blur(6px);background:rgba(255,255,255,.92) }

        .template-preview { background:#f7f7f2;border-radius:10px;padding:10px;margin-top:8px;white-space:pre-wrap;font-family:var(--font-mono);font-size:.82rem;max-height:160px;overflow:auto;border:1px solid var(--border) }

        .context-menu { position:fixed;z-index:100;background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.12);min-width:160px;padding:6px;display:none }
        .context-menu button { display:block;width:100%;text-align:left;padding:8px 12px;border:none;background:none;border-radius:8px;cursor:pointer;font-size:.82rem;font-family:var(--font-body) }
        .context-menu button:hover { background:var(--surface-2) }
        .context-menu .danger { color:#dc3545 }

        .toast-msg { position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:200;background:var(--primary);color:#fff;padding:10px 24px;border-radius:999px;font-size:.85rem;font-weight:700;opacity:0;transition:opacity .25s;pointer-events:none }
        .toast-msg.show { opacity:1 }

        .mini-card { border:1px solid var(--border);border-radius:10px;padding:10px;margin-bottom:8px;background:#fff;cursor:pointer;transition:border-color .15s }
        .mini-card:hover { border-color:var(--secondary) }

        @media(max-width:992px) { .app-layout { grid-template-columns:1fr } .panel { position:fixed;right:0;top:0;bottom:0;width:340px;z-index:20 } }
    </style>
</head>
<body>
<div class="app-layout">
    <div class="canvas-wrap" id="canvasWrap">
        <div class="toolbar">
            <button class="btn btn-primary-enterprise" onclick="addState()">+ State</button>
            <button class="btn btn-ghost" onclick="addRule()">+ Rule</button>
            <button class="btn btn-ghost" onclick="loadFlow()">Refresh</button>
            <a href="{{ route('admin.dashboard', ['section' => 'rules']) }}" class="btn btn-ghost text-decoration-none">← Dashboard</a>
        </div>
        <svg id="svgLayer"></svg>
    </div>

    <div class="panel" id="editorPanel">
        <div class="panel-header">
            <strong class="small" id="panelTitle">Pilih node</strong>
            <button class="btn btn-sm btn-ghost py-0 px-2" onclick="deselectAll()">✕</button>
        </div>
        <div class="panel-body" id="panelContent">
            <div class="panel-empty">👆 Klik node untuk edit state & rules<br>Drag node untuk pindahkan</div>
        </div>
    </div>
</div>

<div class="context-menu" id="contextMenu">
    <button onclick="contextEdit()">✎ Edit</button>
    <button onclick="contextAddRule()">+ Tambah Rule</button>
    <button onclick="contextCopyKey()">📋 Copy Key</button>
    <button class="danger" onclick="contextDelete()">🗑 Hapus</button>
</div>
<div class="toast-msg" id="toast"></div>

{{-- Modal: State --}}
<div class="modal fade" id="stateModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="stateForm" class="modal-content">
            @csrf
            <div class="modal-header"><h2 class="modal-title h4" id="stateModalTitle">Tambah State</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="state_key" id="stateKey">
                <div class="mb-3" id="newKeyGroup"><label class="form-label fw-bold">State Key</label><input class="form-control" name="state_key_new" id="stateKeyNew" placeholder="pesan_konfirmasi" required></div>
                <div class="mb-3"><label class="form-label fw-bold">Label</label><input class="form-control" name="label" id="stateLabel" required></div>
                <div class="mb-3"><label class="form-label fw-bold">Deskripsi</label><textarea class="form-control" name="description" id="stateDesc" rows="2"></textarea></div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label fw-bold">Warna</label><input type="color" class="form-control form-control-color" name="color" id="stateColor" value="#072C2C"></div>
                    <div class="col-md-6 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_entry_point" value="1" id="stateEntry"><label class="form-check-label" for="stateEntry">Entry point</label></div></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary-enterprise">Simpan</button></div>
        </form>
    </div>
</div>

{{-- Modal: Rule --}}
<div class="modal fade" id="ruleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form id="ruleForm" class="modal-content">
            @csrf
            <div class="modal-header"><h2 class="modal-title h4" id="ruleModalTitle">Edit Rule</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="rule_id" id="ruleId">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label fw-bold">Nama Rule</label><input class="form-control" name="nama" id="ruleName" required></div>
                    <div class="col-md-3"><label class="form-label fw-bold">Prioritas</label><input type="number" min="1" class="form-control" name="priority" id="rulePriority" value="10"></div>
                    <div class="col-md-3"><label class="form-label fw-bold">Status</label><select class="form-select" name="is_active" id="ruleActive"><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div>
                    <div class="col-md-4"><label class="form-label fw-bold">Match</label><select class="form-select" name="match_type" id="ruleMatch"><option value="exact">Sama persis</option><option value="contains">Mengandung</option><option value="starts_with">Diawali</option><option value="any">Apa saja</option></select></div>
                    <div class="col-md-4"><label class="form-label fw-bold">Keyword</label><input class="form-control" name="keyword" id="ruleKeyword"></div>
                    <div class="col-md-4"><label class="form-label fw-bold">Action</label><select class="form-select" name="action" id="ruleAction"></select></div>
                    <div class="col-md-6"><label class="form-label fw-bold">Dari State</label><select class="form-select" name="state" id="ruleFrom"></select></div>
                    <div class="col-md-6"><label class="form-label fw-bold">Ke State</label><select class="form-select" name="next_state" id="ruleTo"><option value="">-- Tidak ada --</option></select></div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Template Balasan</label>
                        <div class="d-flex gap-2 mb-2"><select class="form-select" id="templateSelect" onchange="useTemplate()"><option value="">-- Pilih template --</option></select><button type="button" class="btn btn-sm btn-ghost" onclick="clearTemplate()">Clear</button></div>
                        <textarea class="form-control" name="reply_text" id="ruleReply" rows="5" style="font-family:var(--font-mono)" placeholder="Ketik balasan...&#10;Gunakan @{{customer_name}} untuk nama customer."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-danger me-auto" id="ruleDeleteBtn" style="display:none" onclick="deleteRule()">Hapus</button><button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary-enterprise">Simpan</button></div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    let allData, allTemplates, actionOptions, stateList;
    let selectedNode = null, contextNode = null;
    let panX = 0, panY = 0, isPanning = false, startX, startY;
    let dragNode = null, dragOffX, dragOffY;

    // ===== DATA =====
    async function loadFlow() {
        const resp = await fetch('/admin/chatbot/flow-data', { headers: { Accept: 'application/json' } });
        allData = await resp.json();
        allTemplates = allData.templates;
        actionOptions = allData.action_options;
        stateList = allData.nodes.map(n => ({ id: n.id, label: n.label }));
        renderNodes();
        populateDropdowns();
    }

    // ===== RENDER =====
    function renderNodes() {
        const wrap = document.getElementById('canvasWrap');
        // Remove old nodes
        wrap.querySelectorAll('.flow-node').forEach(el => el.remove());

        // Position entry point at center-left
        const entryNode = allData.nodes.find(n => n.is_entry);
        const startX = 80, startY = (wrap.clientHeight / 2) - 40;

        allData.nodes.forEach((node, i) => {
            const el = document.createElement('div');
            el.className = 'flow-node' + (node.is_entry ? ' entry' : '');
            el.id = 'node-' + node.id;
            el.style.background = node.color || '#072C2C';
            el.innerHTML = `
                <div class="node-label">${node.label}</div>
                <div class="node-key">${node.id}</div>
                <div class="node-count">${node.rule_count} rules</div>
                <div class="node-actions">
                    <button class="btn btn-sm btn-ghost py-0 px-1" style="font-size:.7rem;color:#fff;border-color:rgba(255,255,255,.3)" onclick="event.stopPropagation();addRuleToState('${node.id}')">+Rule</button>
                </div>`;

            // Simple positioning: stagger nodes
            const cols = Math.ceil(Math.sqrt(allData.nodes.length));
            const col = i % cols, row = Math.floor(i / cols);
            el.style.left = (startX + col * 220) + 'px';
            el.style.top = (startY + row * 120 - (allData.nodes.length * 10)) + 'px';

            el.addEventListener('mousedown', (e) => onNodeMouseDown(e, node.id, el));
            el.addEventListener('click', (e) => { e.stopPropagation(); selectNode(node.id); });
            el.addEventListener('dblclick', (e) => { e.stopPropagation(); editState(node.id); });
            el.addEventListener('contextmenu', (e) => { e.preventDefault(); e.stopPropagation(); contextNode = node.id; showContextMenu(e.clientX, e.clientY); });

            wrap.appendChild(el);
        });

        drawEdges();
    }

    function drawEdges() {
        const svg = document.getElementById('svgLayer');
        svg.setAttribute('width', '100%');
        svg.setAttribute('height', '100%');
        svg.innerHTML = '';

        allData.edges.forEach(edge => {
            const fromEl = document.getElementById('node-' + edge.from);
            const toEl = document.getElementById('node-' + edge.to);
            if (!fromEl || !toEl) return;

            const fromRect = fromEl.getBoundingClientRect();
            const toRect = toEl.getBoundingClientRect();
            const wrapRect = document.getElementById('canvasWrap').getBoundingClientRect();

            const x1 = fromRect.right - wrapRect.left;
            const y1 = fromRect.top + fromRect.height / 2 - wrapRect.top;
            const x2 = toRect.left - wrapRect.left;
            const y2 = toRect.top + toRect.height / 2 - wrapRect.top;

            const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            line.setAttribute('x1', x1);
            line.setAttribute('y1', y1);
            line.setAttribute('x2', x2);
            line.setAttribute('y2', y2);
            line.setAttribute('marker-end', 'url(#arrowhead)');
            line.style.pointerEvents = 'stroke';
            line.style.cursor = 'pointer';
            line.dataset.edgeId = edge.id;
            line.dataset.from = edge.from;
            line.dataset.to = edge.to;

            line.addEventListener('click', (e) => { e.stopPropagation(); showEdgePanel(edge.id); });
            line.addEventListener('mouseenter', () => line.classList.add('highlight'));
            line.addEventListener('mouseleave', () => line.classList.remove('highlight'));

            svg.appendChild(line);

            // Label di tengah edge
            const mx = (x1 + x2) / 2, my = (y1 + y2) / 2 - 6;
            const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            text.setAttribute('x', mx);
            text.setAttribute('y', my);
            text.setAttribute('text-anchor', 'middle');
            text.setAttribute('font-size', '10');
            text.textContent = edge.label.substring(0, 20);
            svg.appendChild(text);
        });

        // Arrow marker
        if (!document.getElementById('arrowhead')) {
            const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
            const marker = document.createElementNS('http://www.w3.org/2000/svg', 'marker');
            marker.id = 'arrowhead';
            marker.setAttribute('markerWidth', '10');
            marker.setAttribute('markerHeight', '7');
            marker.setAttribute('refX', '10');
            marker.setAttribute('refY', '3.5');
            marker.setAttribute('orient', 'auto');
            marker.innerHTML = '<polygon points="0 0, 10 3.5, 0 7" fill="#94a3b8"/>';
            defs.appendChild(marker);
            svg.insertBefore(defs, svg.firstChild);
        }
    }

    // ===== DRAG NODE =====
    function onNodeMouseDown(e, nodeId, el) {
        if (e.button !== 0) return;
        e.preventDefault();
        dragNode = { id: nodeId, el, offX: e.clientX - el.offsetLeft, offY: e.clientY - el.offsetTop };
        document.addEventListener('mousemove', onDragMove);
        document.addEventListener('mouseup', onDragEnd);
    }

    function onDragMove(e) {
        if (!dragNode) return;
        const wrap = document.getElementById('canvasWrap');
        const rect = wrap.getBoundingClientRect();
        dragNode.el.style.left = Math.max(0, e.clientX - rect.left - dragNode.offX) + 'px';
        dragNode.el.style.top = Math.max(0, e.clientY - rect.top - dragNode.offY) + 'px';
        drawEdges();
    }

    function onDragEnd() {
        dragNode = null;
        document.removeEventListener('mousemove', onDragMove);
        document.removeEventListener('mouseup', onDragEnd);
    }

    // ===== CANVAS PAN =====
    document.getElementById('canvasWrap').addEventListener('mousedown', (e) => {
        if (e.target.closest('.flow-node') || e.target.closest('line')) return;
        isPanning = true;
        startX = e.clientX - panX;
        startY = e.clientY - panY;
        document.getElementById('canvasWrap').classList.add('panning');
    });

    document.addEventListener('mousemove', (e) => {
        if (!isPanning) return;
        panX = e.clientX - startX;
        panY = e.clientY - startY;
        document.querySelectorAll('.flow-node').forEach(el => {
            el.style.transform = `translate(${panX}px, ${panY}px)`;
        });
        // Translate SVG lines too
        document.getElementById('svgLayer').style.transform = `translate(${panX}px, ${panY}px)`;
    });

    document.addEventListener('mouseup', () => {
        if (!isPanning) return;
        isPanning = false;
        document.getElementById('canvasWrap').classList.remove('panning');
    });

    // ===== SELECTION =====
    function selectNode(nodeId) {
        document.querySelectorAll('.flow-node.selected').forEach(el => el.classList.remove('selected'));
        const el = document.getElementById('node-' + nodeId);
        if (el) el.classList.add('selected');
        selectedNode = nodeId;
        showNodePanel(nodeId);
    }

    function deselectAll() {
        document.querySelectorAll('.flow-node.selected').forEach(el => el.classList.remove('selected'));
        selectedNode = null;
        document.getElementById('panelTitle').textContent = 'Pilih node';
        document.getElementById('panelContent').innerHTML = '<div class="panel-empty">👆 Klik node untuk edit state & rules<br>Drag node untuk pindahkan</div>';
    }

    // ===== PANEL =====
    function showNodePanel(stateKey) {
        const node = allData.nodes.find(n => n.id === stateKey);
        if (!node) return;
        const edges = allData.edges.filter(e => e.from === stateKey);

        document.getElementById('panelTitle').textContent = node.label;
        let html = '<div class="mb-2"><span class="badge-soft badge-primary-soft me-1">' + node.rule_count + ' rules</span>';
        if (node.is_entry) html += '<span class="badge bg-warning me-1">ENTRY</span>';
        html += '<span class="small text-muted mono">' + node.id + '</span></div>';
        if (node.description) html += '<p class="small text-muted mb-3">' + node.description + '</p>';
        html += '<div class="d-flex gap-2 mb-3"><button class="btn btn-sm btn-ghost" onclick="editState(\'' + node.id + '\')">Edit</button><button class="btn btn-sm btn-ghost" onclick="addRuleToState(\'' + node.id + '\')">+ Rule</button><button class="btn btn-sm btn-outline-danger" onclick="deleteState(\'' + node.id + '\')">Hapus</button></div>';

        html += '<h4 class="h6">Rules keluar:</h4>';
        if (edges.length === 0) html += '<div class="panel-empty" style="font-size:.8rem">Belum ada rule</div>';
        edges.forEach(e => {
            html += '<div class="mini-card" onclick="showEdgePanel(\'' + e.id + '\')"><div class="d-flex justify-content-between"><strong class="small">' + e.rule_name + '</strong><span class="badge bg-secondary small">' + e.priority + '</span></div><div class="small mono text-muted">→ ' + e.to + '</div><div class="small text-muted">' + (e.keyword || 'any') + (e.action ? ' | ' + e.action : '') + '</div></div>';
        });
        document.getElementById('panelContent').innerHTML = html;
    }

    function showEdgePanel(edgeId) {
        const edge = allData.edges.find(e => e.id === edgeId);
        if (!edge) return;
        document.getElementById('panelTitle').textContent = edge.rule_name;
        let html = '<div class="mb-2"><strong>Dari:</strong> <span class="mono">' + edge.from + '</span></div>';
        html += '<div class="mb-2"><strong>Ke:</strong> <span class="mono">' + edge.to + '</span></div>';
        html += '<div class="mb-2"><strong>Keyword:</strong> ' + (edge.keyword || '<i>any</i>') + ' (' + edge.match_type + ')</div>';
        if (edge.action) html += '<div class="mb-2"><strong>Action:</strong> <span class="badge-soft badge-primary-soft">' + edge.action + '</span></div>';
        if (edge.reply_text) html += '<div class="template-preview">' + escapeHtml(edge.reply_text) + '</div>';
        html += '<div class="d-flex gap-2 mt-3"><button class="btn btn-sm btn-ghost flex-grow-1" onclick="editRule(\'' + edge.rule_id + '\')">Edit</button><button class="btn btn-sm btn-outline-danger" onclick="deleteRuleById(\'' + edge.rule_id + '\')">Hapus</button></div>';
        document.getElementById('panelContent').innerHTML = html;
    }

    // ===== CONTEXT MENU =====
    function showContextMenu(x, y) {
        const menu = document.getElementById('contextMenu');
        menu.style.display = 'block';
        menu.style.left = Math.min(x, window.innerWidth - 180) + 'px';
        menu.style.top = Math.min(y, window.innerHeight - 180) + 'px';
        setTimeout(() => document.addEventListener('click', () => menu.style.display = 'none', { once: true }), 50);
    }
    function contextEdit() { if (contextNode) editState(contextNode); }
    function contextAddRule() { if (contextNode) addRuleToState(contextNode); }
    function contextCopyKey() { if (contextNode) { navigator.clipboard.writeText(contextNode); toast('Disalin: ' + contextNode); } }
    function contextDelete() { if (contextNode) deleteState(contextNode); }

    // ===== DROPDOWNS =====
    function populateDropdowns() {
        const tplSelect = document.getElementById('templateSelect');
        tplSelect.innerHTML = '<option value="">-- Pilih template --</option>';
        allTemplates.forEach(t => { tplSelect.innerHTML += '<option value="' + t.key + '">[' + t.category + '] ' + t.label + '</option>'; });

        const fromSel = document.getElementById('ruleFrom');
        fromSel.innerHTML = '<option value="">-- Semua state --</option>';
        stateList.forEach(s => { fromSel.innerHTML += '<option value="' + s.id + '">' + s.label + '</option>'; });

        const toSel = document.getElementById('ruleTo');
        toSel.innerHTML = '<option value="">-- Tidak ada --</option>';
        stateList.forEach(s => { toSel.innerHTML += '<option value="' + s.id + '">' + s.label + '</option>'; });

        const actionSel = document.getElementById('ruleAction');
        actionSel.innerHTML = '<option value="">-- Tidak ada --</option>';
        for (const [k, v] of Object.entries(actionOptions)) { actionSel.innerHTML += '<option value="' + k + '">' + v + '</option>'; }
    }

    // ===== TEMPLATE =====
    function useTemplate() {
        const key = document.getElementById('templateSelect').value;
        if (!key) return;
        const tpl = allTemplates.find(t => t.key === key);
        if (tpl) document.getElementById('ruleReply').value = tpl.content;
    }
    function clearTemplate() { document.getElementById('ruleReply').value = ''; document.getElementById('templateSelect').value = ''; }

    // ===== MODALS =====
    function addRuleToState(stateKey) { resetRuleForm(); document.getElementById('ruleFrom').value = stateKey; document.getElementById('ruleModalTitle').textContent = 'Tambah Rule'; new bootstrap.Modal('#ruleModal').show(); }
    function addRule() { resetRuleForm(); document.getElementById('ruleModalTitle').textContent = 'Tambah Rule'; new bootstrap.Modal('#ruleModal').show(); }
    function resetRuleForm() {
        ['ruleId','ruleName','ruleKeyword','ruleReply'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('ruleDeleteBtn').style.display = 'none';
        document.getElementById('rulePriority').value = '50';
        document.getElementById('ruleActive').value = '1';
        document.getElementById('ruleMatch').value = 'any';
        document.getElementById('ruleAction').value = '';
        document.getElementById('ruleFrom').value = '';
        document.getElementById('ruleTo').value = '';
        document.getElementById('templateSelect').value = '';
    }

    function editRule(ruleId) {
        const edge = allData.edges.find(e => String(e.rule_id) === String(ruleId));
        if (!edge) return;
        document.getElementById('ruleId').value = ruleId;
        document.getElementById('ruleModalTitle').textContent = 'Edit: ' + edge.rule_name;
        document.getElementById('ruleDeleteBtn').style.display = 'block';
        document.getElementById('ruleName').value = edge.rule_name;
        document.getElementById('rulePriority').value = edge.priority;
        document.getElementById('ruleActive').value = edge.is_active ? '1' : '0';
        document.getElementById('ruleMatch').value = edge.match_type;
        document.getElementById('ruleKeyword').value = edge.keyword || '';
        document.getElementById('ruleAction').value = edge.action || '';
        document.getElementById('ruleFrom').value = edge.from;
        document.getElementById('ruleTo').value = edge.to;
        document.getElementById('ruleReply').value = edge.reply_text || '';
        document.getElementById('templateSelect').value = '';
        new bootstrap.Modal('#ruleModal').show();
    }

    function editState(stateKey) {
        const node = allData.nodes.find(n => n.id === stateKey);
        if (!node) return;
        document.getElementById('stateKey').value = stateKey;
        document.getElementById('newKeyGroup').style.display = 'none';
        document.getElementById('stateKeyNew').required = false;
        document.getElementById('stateModalTitle').textContent = 'Edit State';
        document.getElementById('stateLabel').value = node.label;
        document.getElementById('stateDesc').value = node.description || '';
        document.getElementById('stateColor').value = node.color;
        document.getElementById('stateEntry').checked = node.is_entry;
        new bootstrap.Modal('#stateModal').show();
    }

    function addState() {
        document.getElementById('stateKey').value = '';
        document.getElementById('newKeyGroup').style.display = 'block';
        document.getElementById('stateKeyNew').required = true;
        document.getElementById('stateModalTitle').textContent = 'Tambah State';
        document.getElementById('stateLabel').value = '';
        document.getElementById('stateDesc').value = '';
        document.getElementById('stateColor').value = '#072C2C';
        document.getElementById('stateEntry').checked = false;
        new bootstrap.Modal('#stateModal').show();
    }

    function deleteState(key) {
        if (!confirm('Hapus state "' + key + '"?')) return;
        fetch('/admin/chatbot/flow/state/' + key, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf } })
            .then(r => { if (r.ok) { toast('State dihapus'); loadFlow(); } });
    }

    function deleteRule() {
        const id = document.getElementById('ruleId').value;
        if (!id || !confirm('Hapus rule?')) return;
        doDeleteRule(id);
    }
    function deleteRuleById(id) { if (confirm('Hapus rule?')) doDeleteRule(id); }
    function doDeleteRule(id) {
        fetch('/admin/chatbot-rules/' + id, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf } })
            .then(r => { if (r.ok) { bootstrap.Modal.getInstance('#ruleModal')?.hide(); toast('Rule dihapus'); loadFlow(); } });
    }

    // ===== FORMS =====
    document.getElementById('stateForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const f = e.target;
        const existingKey = f.state_key.value, newKey = f.state_key_new.value;
        const key = existingKey || newKey;
        if (!key) return toast('State key harus diisi');
        const method = existingKey ? 'PATCH' : 'POST';
        const url = existingKey ? '/admin/chatbot/flow/state/' + existingKey : '/admin/chatbot/flow/state';
        const body = { label: f.label.value, description: f.description.value, color: f.color.value, is_entry_point: f.is_entry_point.checked ? '1' : '0' };
        if (!existingKey) body.state_key = newKey;
        const resp = await fetch(url, { method, headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
        if (resp.ok) { bootstrap.Modal.getInstance('#stateModal').hide(); toast('State disimpan'); loadFlow(); }
    });

    document.getElementById('ruleForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const f = e.target;
        const id = f.rule_id.value;
        const method = id ? 'PATCH' : 'POST';
        const url = id ? '/admin/chatbot-rules/' + id : '/admin/chatbot-rules';
        const body = { nama: f.nama.value, keyword: f.keyword.value, match_type: f.match_type.value, state: f.state.value, next_state: f.next_state.value, action: f.action.value, reply_text: f.reply_text.value, priority: f.priority.value, is_active: f.is_active.value };
        if (!id) body.is_active = body.is_active || '1';
        const resp = await fetch(url, { method, headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
        if (resp.ok) { bootstrap.Modal.getInstance('#ruleModal').hide(); toast('Rule disimpan'); loadFlow(); }
    });

    // ===== TOAST =====
    function toast(msg) { const el = document.getElementById('toast'); el.textContent = msg; el.classList.add('show'); setTimeout(() => el.classList.remove('show'), 2000); }

    // ===== UTILS =====
    function escapeHtml(s) { return String(s||'').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c])); }

    // Resize handler
    window.addEventListener('resize', () => { if (allData) drawEdges(); });

    loadFlow();
</script>
</body>
</html>
