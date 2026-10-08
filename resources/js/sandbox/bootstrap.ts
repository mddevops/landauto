/**
 * Landflow-owned script that runs first inside the opaque-origin sandbox (ADR-008 §3–§4). It is
 * plain ES2017 source inlined into the srcdoc, so it must stay self-contained: render the template
 * with escaped values, expose `window.landflow` and talk to the parent only through the allowlisted
 * `landflow:*` messages.
 */
export const SANDBOX_BOOTSTRAP = String.raw`(function () {
    'use strict';
    var dataNode = document.getElementById('landflow-data');
    var data = JSON.parse(dataNode.textContent || '{}');
    dataNode.parentNode.removeChild(dataNode);
    var root = document.getElementById('landflow-root');
    var actions = Array.isArray(data.actions) ? data.actions : [];

    function post(type, payload) {
        var message = { type: type };
        Object.keys(payload).forEach(function (key) { message[key] = payload[key]; });
        try { window.parent.postMessage(message, data.parentOrigin); } catch (error) {}
    }

    function report(message) {
        post('landflow:error', { message: String(message || 'Ошибка выполнения').slice(0, 500) });
    }

    window.addEventListener('error', function (event) { report(event.message); });
    window.addEventListener('unhandledrejection', function (event) {
        report(event.reason && event.reason.message ? event.reason.message : event.reason);
    });

    var TAG = /\{\{\s*(#if|#each|else|\/if|\/each)?\s*([a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*)?\s*\}\}/g;

    function parse(source) {
        var top = { kind: 'root', children: [], otherwise: [], inElse: false };
        var stack = [top];
        var last = 0;
        var match;

        function target() {
            var node = stack[stack.length - 1];
            return node.inElse ? node.otherwise : node.children;
        }

        TAG.lastIndex = 0;

        while ((match = TAG.exec(source)) !== null) {
            if (match.index > last) { target().push({ text: source.slice(last, match.index) }); }
            last = TAG.lastIndex;
            var op = match[1];
            var path = match[2];
            var node = stack[stack.length - 1];

            if (!op) {
                if (!path) { throw new Error('Пустая конструкция {{ }}.'); }
                target().push({ path: path });
            } else if (op === '#if' || op === '#each') {
                if (!path) { throw new Error('Укажите поле в {{' + op + ' …}}.'); }
                var section = { kind: op.slice(1), path: path, children: [], otherwise: [], inElse: false };
                target().push(section);
                stack.push(section);
            } else if (op === 'else') {
                if (node.kind !== 'if' || node.inElse || path) { throw new Error('{{else}} вне {{#if …}}.'); }
                node.inElse = true;
            } else {
                if (node.kind !== op.slice(1) || path || stack.length === 1) { throw new Error('Лишний {{' + op + '}}.'); }
                stack.pop();
            }
        }

        if (stack.length > 1) { throw new Error('Не закрыт блок {{#' + stack[stack.length - 1].kind + ' …}}.'); }
        target().push({ text: source.slice(last) });

        return top.children;
    }

    function has(value, key) {
        return value !== null && typeof value === 'object' && Object.prototype.hasOwnProperty.call(value, key);
    }

    function lookup(path, scopes) {
        var parts = path.split('.');
        var value;

        for (var i = scopes.length - 1; i >= 0; i--) {
            if (has(scopes[i], parts[0])) { value = scopes[i][parts[0]]; break; }
        }

        for (var j = 1; j < parts.length; j++) { value = has(value, parts[j]) ? value[parts[j]] : undefined; }

        return value;
    }

    function escape(value) {
        return String(value).replace(/[&<>"'${'`'}]/g, function (char) { return '&#' + char.charCodeAt(0) + ';'; });
    }

    function truthy(value) {
        return Array.isArray(value) ? value.length > 0 : Boolean(value);
    }

    function render(nodes, scopes) {
        return nodes.map(function (node) {
            if (node.text !== undefined) { return node.text; }

            if (!node.kind) {
                var value = lookup(node.path, scopes);
                return value === null || value === undefined || typeof value === 'object' ? '' : escape(value);
            }

            if (node.kind === 'if') { return render(truthy(lookup(node.path, scopes)) ? node.children : node.otherwise, scopes); }

            var items = lookup(node.path, scopes);
            return Array.isArray(items) ? items.map(function (item) { return render(node.children, scopes.concat([item])); }).join('') : '';
        }).join('');
    }

    function freeze(value) {
        if (value !== null && typeof value === 'object') {
            Object.keys(value).forEach(function (key) { freeze(value[key]); });
            Object.freeze(value);
        }
        return value;
    }

    var props = freeze(data.props && typeof data.props === 'object' ? data.props : {});

    try {
        root.innerHTML = render(parse(String(data.template || '')), [props]);
    } catch (error) {
        root.innerHTML = '';
        root.setAttribute('data-landflow-failed', '');
        report(error.message);
    }

    var lastHeight = -1;

    function resize() {
        var height = Math.ceil(root.getBoundingClientRect().height);
        if (height !== lastHeight) {
            lastHeight = height;
            post('landflow:resize', { height: height });
        }
    }

    function action(key) {
        if (actions.indexOf(key) !== -1) { post('landflow:action', { key: key }); }
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target && event.target.closest ? event.target.closest('[data-landflow-action]') : null;
        if (trigger) {
            event.preventDefault();
            action(trigger.getAttribute('data-landflow-action'));
        }
    });

    if (typeof ResizeObserver === 'function') { new ResizeObserver(resize).observe(root); }
    window.addEventListener('load', resize);
    resize();

    Object.defineProperty(window, 'landflow', {
        value: Object.freeze({ props: props, root: root, action: action, resize: resize }),
    });
})();`;
