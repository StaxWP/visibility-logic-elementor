var StaxConditions = (() => {
  var __defProp = Object.defineProperty;
  var __getOwnPropDesc = Object.getOwnPropertyDescriptor;
  var __getOwnPropNames = Object.getOwnPropertyNames;
  var __hasOwnProp = Object.prototype.hasOwnProperty;
  var __export = (target, all) => {
    for (var name in all)
      __defProp(target, name, { get: all[name], enumerable: true });
  };
  var __copyProps = (to, from, except, desc) => {
    if (from && typeof from === "object" || typeof from === "function") {
      for (let key of __getOwnPropNames(from))
        if (!__hasOwnProp.call(to, key) && key !== except)
          __defProp(to, key, { get: () => from[key], enumerable: !(desc = __getOwnPropDesc(from, key)) || desc.enumerable });
    }
    return to;
  };
  var __toCommonJS = (mod) => __copyProps(__defProp({}, "__esModule", { value: true }), mod);

  // js/src/index.js
  var index_exports = {};
  __export(index_exports, {
    builderLabels: () => builderLabels,
    contextLabel: () => contextLabel,
    createBuilder: () => createBuilder,
    createPreviewBar: () => createPreviewBar,
    previewLabels: () => previewLabels,
    renderTrace: () => renderTrace,
    summary: () => summary_exports,
    tree: () => tree_exports,
    version: () => version,
    warnings: () => warnings_exports
  });

  // js/src/tree.js
  var tree_exports = {};
  __export(tree_exports, {
    VERSION: () => VERSION,
    allConditions: () => allConditions,
    clone: () => clone,
    filledGroups: () => filledGroups,
    find: () => find,
    isEmpty: () => isEmpty,
    make: () => make,
    makeId: () => makeId,
    normalize: () => normalize
  });
  var VERSION = 1;
  function makeId() {
    return "c" + Math.random().toString(36).slice(2, 9);
  }
  function make(action) {
    return { v: VERSION, action: action === "hide" ? "hide" : "show", draft: false, groups: [{ conditions: [] }] };
  }
  function normalize(data) {
    if (typeof data === "string" && data !== "") {
      try {
        data = JSON.parse(data);
      } catch (e) {
        return null;
      }
    }
    if (!data || typeof data !== "object" || !Array.isArray(data.groups)) {
      return null;
    }
    return {
      v: VERSION,
      action: data.action === "hide" ? "hide" : "show",
      draft: !!data.draft,
      groups: data.groups.map((group, g) => ({
        conditions: (group && Array.isArray(group.conditions) ? group.conditions : []).filter((c) => c && typeof c.type === "string" && c.type !== "").map((c, i) => ({
          id: c.id !== void 0 && c.id !== null && String(c.id) !== "" ? String(c.id) : "c" + g + "_" + i,
          type: c.type.toLowerCase().replace(/[^a-z0-9_-]/g, ""),
          values: c.values && typeof c.values === "object" && !Array.isArray(c.values) ? c.values : {}
        }))
      }))
    };
  }
  function filledGroups(tree) {
    return (tree && tree.groups || []).filter((g) => g.conditions && g.conditions.length);
  }
  function allConditions(tree) {
    return filledGroups(tree).reduce((all, g) => all.concat(g.conditions), []);
  }
  function isEmpty(tree) {
    return !tree.draft && allConditions(tree).length === 0;
  }
  function clone(tree) {
    return JSON.parse(JSON.stringify(tree));
  }
  function find(tree, id) {
    for (let g = 0; g < tree.groups.length; g++) {
      const i = tree.groups[g].conditions.findIndex((c) => c.id === id);
      if (i !== -1) {
        return { group: g, index: i, condition: tree.groups[g].conditions[i] };
      }
    }
    return null;
  }

  // js/src/summary.js
  var summary_exports = {};
  __export(summary_exports, {
    conditionText: () => conditionText,
    defaultLabels: () => defaultLabels,
    defaults: () => defaults,
    fieldDefault: () => fieldDefault,
    fieldOf: () => fieldOf,
    format: () => format,
    render: () => render,
    sentence: () => sentence,
    short: () => short,
    shown: () => shown,
    sprintf: () => sprintf,
    toList: () => toList,
    typeOf: () => typeOf
  });
  var defaultLabels = {
    show: "Shown only when %s",
    hide: "Hidden when %s",
    and: " and ",
    or: " or ",
    draft: "Hidden from everyone",
    none: "No conditions yet",
    more: "%1$s and %2$d more",
    yes: "yes",
    no: "no",
    short_show: "%s",
    short_hide: "Hidden: %s",
    short_more: "%1$s +%2$d",
    users: "%d users",
    posts: "%d items",
    next_day: " (next day)",
    rows: "%d rules"
  };
  function sprintf(format2, ...args) {
    let i = 0;
    return String(format2).replace(/%(?:(\d+)\$)?([sd])/g, (m, pos, kind) => {
      const value = pos ? args[Number(pos) - 1] : args[i++];
      return kind === "d" ? String(parseInt(value, 10) || 0) : String(value === void 0 ? "" : value);
    });
  }
  function typeOf(registry, slug) {
    return (registry.types || []).find((t) => t.slug === slug) || null;
  }
  function fieldOf(registry, slug, key) {
    const type = typeOf(registry, slug);
    return type ? (type.fields || []).find((f) => f.key === key) || null : null;
  }
  function fieldDefault(field) {
    if (field && Object.prototype.hasOwnProperty.call(field, "default")) {
      return field.default;
    }
    const type = field && field.type || "text";
    if (type === "switch") {
      return false;
    }
    return ["multiselect", "list", "users", "posts", "repeater"].indexOf(type) !== -1 ? [] : "";
  }
  function defaults(registry, slug) {
    const type = typeOf(registry, slug);
    const out = {};
    (type && type.fields || []).forEach((f) => {
      out[f.key] = fieldDefault(f);
    });
    return out;
  }
  function shown(field, values, fields) {
    if (!field.show_if) {
      return true;
    }
    return Object.keys(field.show_if).every((key) => {
      let current;
      if (Object.prototype.hasOwnProperty.call(values, key)) {
        current = values[key];
      } else {
        const other = (fields || []).find((f) => f.key === key);
        current = other ? fieldDefault(other) : "";
      }
      if (typeof current === "boolean") {
        current = current ? "yes" : "";
      }
      const scalar = current === null || typeof current === "object" ? "" : String(current);
      return [].concat(field.show_if[key]).map(String).indexOf(scalar) !== -1;
    });
  }
  function toList(value) {
    const items = Array.isArray(value) ? value : String(value === null || value === void 0 ? "" : value).split(",");
    return items.map((item2) => item2 === null || typeof item2 === "object" ? "" : String(item2).trim()).filter((item2) => item2.length);
  }
  function optionLabel(value, field) {
    if (field.summary_options && field.summary_options[value] !== void 0) {
      return String(field.summary_options[value]);
    }
    const option = (field.options || []).find((o) => String(o.value) === value);
    return option ? String(option.label) : value;
  }
  function lcfirst(text) {
    if (text.length > 1 && /[A-Z]/.test(text.charAt(1))) {
      return text;
    }
    return text.charAt(0).toLowerCase() + text.slice(1);
  }
  function join(items, glue, labels) {
    if (items.length > 3) {
      return sprintf(labels.more, items.slice(0, 3).join(", "), items.length - 3);
    }
    return items.join(glue);
  }
  function format(value, field, labels, formatters, modifier) {
    labels = Object.assign({}, defaultLabels, labels || {});
    const type = field.type || "text";
    let text;
    if (formatters && typeof formatters[type] === "function") {
      text = String(formatters[type](value, field));
      return modifier === "lower" ? lcfirst(text) : text;
    }
    switch (type) {
      case "switch":
        text = value === true || value === "yes" || value === 1 || value === "1" ? labels.yes : "";
        break;
      case "users":
      case "posts":
        text = Array.isArray(value) && value.length ? sprintf(labels[type], value.length) : "";
        break;
      case "repeater":
        text = Array.isArray(value) && value.length ? sprintf(labels.rows, value.length) : "";
        break;
      case "multiselect":
      case "list": {
        const items = (Array.isArray(value) ? value : toList(value)).filter((item2) => item2 !== null && typeof item2 !== "object").map(String).filter((item2) => item2.length).map((item2) => optionLabel(item2, field));
        text = join(items, modifier === "or" ? labels.or : ", ", labels);
        break;
      }
      case "select":
        text = value !== null && value !== void 0 && typeof value !== "object" && String(value) !== "" ? optionLabel(String(value), field) : "";
        break;
      case "date":
        text = value !== null && value !== void 0 && typeof value !== "object" ? String(value).replace("T", " ").trim() : "";
        break;
      default:
        text = value !== null && value !== void 0 && typeof value !== "object" ? String(value).trim() : "";
    }
    return modifier === "lower" ? lcfirst(text) : text;
  }
  function placeholders(text, slug, registry, values, labels, formatters, state) {
    return text.replace(/\{([a-z0-9_]+)(?:\|([a-z]+)(?::([a-z0-9_]+))?)?\}/g, (m, key, modifier, other) => {
      const field = fieldOf(registry, slug, key) || { type: "text" };
      const value = Object.prototype.hasOwnProperty.call(values, key) ? values[key] : null;
      let out = format(value, field, labels, formatters, modifier === "after" ? "" : modifier || "");
      const scalar = (v) => v !== null && v !== void 0 && typeof v !== "object";
      if (modifier === "after" && out !== "" && other && scalar(values[other]) && String(values[other]) !== "" && scalar(value) && String(value) < String(values[other])) {
        out += Object.assign({}, defaultLabels, labels || {}).next_day;
      }
      if (out === "") {
        state.empty = true;
      }
      return out;
    });
  }
  function render(template, condition, registry, labels, formatters) {
    labels = Object.assign({}, defaultLabels, labels || {});
    const values = Object.assign({}, defaults(registry, condition.type), condition.values || {});
    if (template && typeof template === "object") {
      const value = values[template.field];
      const key = value !== null && value !== void 0 && typeof value !== "object" ? String(value) : "";
      const cases = template.cases || {};
      template = cases[key] !== void 0 ? cases[key] : cases["*"] !== void 0 ? cases["*"] : "";
    }
    template = String(template || "").replace(/\[([^[\]]*)\]/g, (m, inner) => {
      const state = { empty: false };
      const out = placeholders(inner, condition.type, registry, values, labels, formatters, state);
      return state.empty ? "" : out;
    });
    return placeholders(template, condition.type, registry, values, labels, formatters, { empty: false }).replace(/\s+/g, " ").trim();
  }
  function conditionText(condition, registry, labels, formatters) {
    const type = typeOf(registry, condition.type);
    if (!type) {
      const words = String(condition.type).replace(/_/g, " ");
      return words.charAt(0).toUpperCase() + words.slice(1);
    }
    const text = render(type.summary, condition, registry, labels, formatters);
    return text !== "" ? text : String(type.label);
  }
  function sentence(tree, registry, labels, formatters) {
    labels = Object.assign({}, defaultLabels, labels || {});
    if (tree.draft) {
      return labels.draft;
    }
    const groups = filledGroups(tree);
    if (!groups.length) {
      return labels.none;
    }
    const parts = groups.map((group) => {
      const texts = group.conditions.map((c) => conditionText(c, registry, labels, formatters));
      const text = texts.join(labels.and);
      return groups.length > 1 && texts.length > 1 ? "(" + text + ")" : text;
    });
    return sprintf(tree.action === "hide" ? labels.hide : labels.show, parts.join(labels.or));
  }
  function short(tree, registry, labels, formatters) {
    labels = Object.assign({}, defaultLabels, labels || {});
    if (tree.draft) {
      return labels.draft;
    }
    const all = allConditions(tree);
    if (!all.length) {
      return labels.none;
    }
    const type = typeOf(registry, all[0].type);
    let first = render(type && type.short ? type.short : type ? type.summary : "", all[0], registry, labels, formatters);
    if (first === "") {
      first = type ? String(type.label) : all[0].type;
    }
    if (all.length > 1) {
      first = sprintf(labels.short_more, first, all.length - 1);
    }
    return sprintf(tree.action === "hide" ? labels.short_hide : labels.short_show, first);
  }

  // js/src/warnings.js
  var warnings_exports = {};
  __export(warnings_exports, {
    check: () => check
  });
  function isEmptyValue(value) {
    if (Array.isArray(value)) {
      return value.filter((v) => v !== null && v !== "" && !(Array.isArray(v) && !v.length)).length === 0;
    }
    return value === null || value === void 0 || value === "" || value === false || value === 0 || value === "0";
  }
  function dateOf(type, which, values) {
    const decl = type[which];
    if (!decl || !decl.field) {
      return "";
    }
    const when = decl.when || {};
    for (const key of Object.keys(when)) {
      if (values[key] === void 0 || [].concat(when[key]).map(String).indexOf(String(values[key])) === -1) {
        return "";
      }
    }
    const raw = values[decl.field];
    const value = raw !== null && raw !== void 0 && typeof raw !== "object" ? String(raw).replace("T", " ").trim() : "";
    if (!/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2})?/.test(value)) {
      return "";
    }
    return value.length >= 16 ? value.slice(0, 16) : value.slice(0, 10) + " 00:00";
  }
  function item(code, condition, value) {
    return { code, id: condition ? condition.id : "", type: condition ? condition.type : "", value: value || "" };
  }
  function never(group, registry) {
    const seen = {};
    const out = [];
    group.conditions.forEach((condition) => {
      const type = typeOf(registry, condition.type);
      if (!type || !type.exclusive) {
        return;
      }
      const values = Object.assign({}, defaults(registry, condition.type), condition.values);
      const field = fieldOf(registry, condition.type, type.exclusive);
      if (field && !shown(field, values, type.fields)) {
        return;
      }
      const set = toList(values[type.exclusive] || []);
      if (!set.length) {
        return;
      }
      if (seen[condition.type] && !seen[condition.type].some((v) => set.indexOf(v) !== -1)) {
        out.push(condition);
        return;
      }
      seen[condition.type] = seen[condition.type] ? seen[condition.type].filter((v) => set.indexOf(v) !== -1) : set;
    });
    return out;
  }
  function check(tree, registry, context) {
    context = context || {};
    const out = [];
    const now = context.now ? String(context.now) : "";
    let visitor = false;
    if (tree.draft) {
      out.push(item("draft"));
    }
    filledGroups(tree).forEach((group) => {
      group.conditions.forEach((condition) => {
        const type = typeOf(registry, condition.type);
        if (!type) {
          return;
        }
        const values = Object.assign({}, defaults(registry, condition.type), condition.values);
        if (type.locked) {
          out.push(item("locked", condition));
        } else if (type.disabled) {
          out.push(item("disabled", condition));
        }
        for (const field of type.fields || []) {
          if (field.required && shown(field, values, type.fields) && isEmptyValue(values[field.key])) {
            out.push(item("incomplete", condition));
            break;
          }
        }
        if (now) {
          const end = dateOf(type, "expires", values);
          if (end && end < now) {
            out.push(item("expired", condition, end));
          }
          const start = dateOf(type, "starts", values);
          if (start && start > now) {
            out.push(item("future", condition, start));
          }
        }
        if (type.visitor) {
          visitor = true;
        }
      });
      never(group, registry).forEach((condition) => out.push(item("never", condition)));
    });
    if (visitor && context.cache) {
      out.push(item("cache"));
    }
    return out;
  }

  // js/src/builder.js
  var builderLabels = Object.assign({}, defaultLabels, {
    builder: "Visibility rules",
    actionLabel: "Action",
    actionShow: "Show",
    actionHide: "Hide",
    actionDraft: "Hide from everyone",
    when: "this element when",
    allOf: "All of these",
    groupN: "Group %d: all of these",
    orLabel: "or",
    addCondition: "Add condition",
    addGroup: 'Add "or" group',
    removeGroup: "Remove group %d",
    edit: "Edit",
    remove: "Remove",
    removeCondition: "Remove condition: %s",
    duplicate: "Duplicate",
    moveTo: "Move to",
    moveGroup: "Group %d",
    moveNew: 'New "or" group',
    draft: "Hidden from everyone",
    draftHelp: "Ignores the conditions and keeps them for later. You still see it in the editor.",
    search: "Search conditions",
    all: "All",
    presets: "Start from a preset",
    close: "Close",
    pro: "Pro",
    proFeature: "Pro feature",
    lockedText: "%s needs Visibility Logic Pro.",
    learnMore: "Learn more",
    noResults: "No condition matches your search.",
    readOnly: "This condition is edited in the classic settings.",
    addValue: "Add",
    removeValue: "Remove %s",
    addRow: "Add rule",
    removeRow: "Remove rule %d",
    rowN: "Rule %d",
    searchUsers: "Search users",
    searchPosts: "Search pages and posts",
    noUsers: "No users found.",
    filterOptions: "Filter options",
    warnings: {
      draft: "Hidden from everyone: visitors never see it.",
      locked: "%s needs a Pro license; it is kept and still applies.",
      disabled: "%s is turned off in the settings; it does not apply.",
      incomplete: "%s is not complete yet.",
      expired: "The date range of %1$s ended on %2$s.",
      future: "%1$s starts on %2$s.",
      never: "This group can never be true: %s conflicts with another condition.",
      cache: "This rule depends on the visitor. If this page is cached for everyone, visitors may see the wrong version."
    }
  });
  function h(tag, attrs, children) {
    const node = document.createElement(tag);
    Object.keys(attrs || {}).forEach((key) => {
      const value = attrs[key];
      if (value === null || value === void 0 || value === false) {
        return;
      }
      if (key === "class") {
        node.className = value;
      } else if (key === "text") {
        node.textContent = value;
      } else if (key.indexOf("on") === 0 && typeof value === "function") {
        node.addEventListener(key.slice(2).toLowerCase(), value);
      } else if (key === "value") {
        node.value = value;
      } else if (key === "checked" || key === "selected") {
        node[key] = !!value;
      } else {
        node.setAttribute(key, value === true ? "" : value);
      }
    });
    const add = (child) => {
      if (child === null || child === void 0 || child === false) {
        return;
      }
      if (Array.isArray(child)) {
        child.forEach(add);
        return;
      }
      node.appendChild(typeof child === "string" || typeof child === "number" ? document.createTextNode(String(child)) : child);
    };
    add(children);
    return node;
  }
  var instances = 0;
  function createBuilder(root, options) {
    const opts = Object.assign({ registry: { types: [], categories: [], presets: [] }, labels: {}, context: {}, settings: null }, options || {});
    const L = Object.assign({}, builderLabels, opts.labels || {});
    L.warnings = Object.assign({}, builderLabels.warnings, opts.labels && opts.labels.warnings || {});
    const uid = "stxc" + ++instances;
    const registry = opts.registry;
    const state = {
      tree: normalize(opts.value) || make("show"),
      open: {},
      // condition id => expanded
      picker: null,
      // group index the picker is open for
      query: "",
      category: "",
      lockedMessage: "",
      userCache: {}
    };
    root.classList.add("stxc");
    const fmt = Object.assign({
      users: (value) => Array.isArray(value) ? value.map((id) => state.userCache[id] || "#" + id).join(", ") : "",
      posts: (value) => Array.isArray(value) ? value.map((id) => state.userCache[id] || "#" + id).join(", ") : ""
    }, opts.formatters || {});
    function emit() {
      if (typeof opts.onChange === "function") {
        opts.onChange(clone(state.tree));
      }
    }
    function update(mutator, rerender) {
      const next = clone(state.tree);
      mutator(next);
      state.tree = next;
      if (rerender === false) {
        refreshText();
      } else {
        render2();
      }
      emit();
    }
    function refreshText() {
      const summary = root.querySelector(".stxc-summary");
      if (summary) {
        summary.textContent = sentence(state.tree, registry, L, fmt);
      }
      allConditions(state.tree).forEach((c) => {
        const head = root.querySelector('[data-head="' + c.id + '"] .stxc-cond-text');
        if (head) {
          head.textContent = conditionText(c, registry, L, fmt);
        }
      });
      const warnings = root.querySelector(".stxc-warnings-wrap");
      if (warnings) {
        warnings.replaceWith(renderWarnings());
      }
    }
    function labelOfType(slug) {
      const type = typeOf(registry, slug);
      return type ? type.label : slug;
    }
    function renderWarnings() {
      const list = check(state.tree, registry, opts.context || {});
      const wrap = h("div", { class: "stxc-warnings-wrap" });
      if (!list.length) {
        return wrap;
      }
      wrap.appendChild(
        h("ul", { class: "stxc-warnings" }, list.map((w) => {
          const name = w.type ? labelOfType(w.type) : "";
          return h("li", { class: "stxc-warning stxc-warning--" + w.code, "data-code": w.code }, sprintf(L.warnings[w.code] || w.code, name, w.value));
        }))
      );
      return wrap;
    }
    function inputId(condition, key, suffix) {
      return uid + "-" + condition.id + "-" + key + (suffix ? "-" + suffix : "");
    }
    function setValue(condition, key, value, rerender) {
      update((tree) => {
        const found = find(tree, condition.id);
        if (found) {
          found.condition.values = Object.assign({}, found.condition.values, { [key]: value });
        }
      }, rerender);
    }
    function fieldNode(condition, field, values, readOnly, onSet) {
      const id = inputId(condition, field.key);
      const value = values[field.key];
      const help = field.help ? h("p", { class: "stxc-help", id: id + "-help" }, field.help) : null;
      const described = field.help ? id + "-help" : null;
      const label = h("label", { class: "stxc-label", for: id }, field.label);
      const set = onSet || ((v, rerender) => setValue(condition, field.key, v, rerender));
      switch (field.type) {
        case "switch":
          return h("div", { class: "stxc-field stxc-field--switch" }, [
            h("input", {
              type: "checkbox",
              id,
              "data-fk": id,
              checked: value === true || value === "yes",
              disabled: readOnly,
              "aria-describedby": described,
              onChange: (e) => set(e.target.checked, true)
            }),
            h("label", { for: id }, field.label),
            help
          ]);
        case "select":
          return h("div", { class: "stxc-field" }, [
            label,
            h(
              "select",
              { id, "data-fk": id, disabled: readOnly, "aria-describedby": described, onChange: (e) => set(e.target.value, true) },
              (field.options || []).map((o) => h("option", { value: o.value, selected: String(value) === o.value, disabled: o.disabled }, o.label))
            ),
            help
          ]);
        case "multiselect": {
          const list = Array.isArray(value) ? value.map(String) : [];
          const many = (field.options || []).length > 10;
          const box = h("div", { class: "stxc-checks" + (many ? " stxc-checks--scroll" : "") }, (field.options || []).map((o, i) => {
            const cid = id + "-" + i;
            return h("div", { class: "stxc-check", "data-label": o.label.toLowerCase() }, [
              h("input", {
                type: "checkbox",
                id: cid,
                "data-fk": cid,
                checked: list.indexOf(o.value) !== -1,
                disabled: readOnly || o.disabled,
                onChange: (e) => {
                  const next = list.filter((v) => v !== o.value);
                  if (e.target.checked) {
                    next.push(o.value);
                  }
                  set(next, true);
                }
              }),
              h("label", { for: cid }, o.label)
            ]);
          }));
          const filter = many ? h("input", {
            type: "search",
            class: "stxc-filter",
            "aria-label": L.filterOptions + ": " + field.label,
            placeholder: L.filterOptions,
            onInput: (e) => {
              const q = e.target.value.toLowerCase();
              box.querySelectorAll(".stxc-check").forEach((row) => {
                row.hidden = q !== "" && row.getAttribute("data-label").indexOf(q) === -1;
              });
            }
          }) : null;
          return h("fieldset", { class: "stxc-field stxc-field--multi", "aria-describedby": described }, [h("legend", { class: "stxc-label" }, field.label), filter, box, help]);
        }
        case "list":
        case "users":
        case "posts":
          return chipsField(condition, field, value, readOnly, id, help, described, set);
        case "repeater":
          return repeaterField(condition, field, value, readOnly, id, help);
        case "textarea":
          return h("div", { class: "stxc-field" }, [
            label,
            h("textarea", { id, "data-fk": id, rows: 3, disabled: readOnly, "aria-describedby": described, onInput: (e) => set(e.target.value, false) }, value ? String(value) : ""),
            help
          ]);
        default: {
          const types = { date: "datetime-local", time: "time", number: "number" };
          const shownValue = field.type === "date" ? String(value || "").replace(" ", "T") : value === void 0 || value === null ? "" : String(value);
          return h("div", { class: "stxc-field" }, [
            label,
            h("input", {
              type: types[field.type] || "text",
              id,
              "data-fk": id,
              value: shownValue,
              placeholder: field.placeholder || null,
              disabled: readOnly,
              "aria-describedby": described,
              onInput: (e) => set(field.type === "date" ? e.target.value.replace("T", " ") : e.target.value, false)
            }),
            help
          ]);
        }
      }
    }
    function chipsField(condition, field, value, readOnly, id, help, described, set) {
      const list = Array.isArray(value) ? value.map(String) : value ? String(value).split(",").map((v) => v.trim()).filter(Boolean) : [];
      const searchFn = opts.search && typeof opts.search[field.type] === "function" ? opts.search[field.type] : null;
      const isUsers = (field.type === "users" || field.type === "posts") && !!searchFn;
      const chipLabel = (v) => field.type === "users" || field.type === "posts" ? state.userCache[v] || "#" + v : v;
      const chips = h("ul", { class: "stxc-chips", "aria-label": field.label }, list.map((v) => h("li", { class: "stxc-chip" }, [
        h("span", null, chipLabel(v)),
        readOnly ? null : h("button", {
          type: "button",
          class: "stxc-chip-x",
          "data-fk": id + "-x-" + v,
          "aria-label": sprintf(L.removeValue, chipLabel(v)),
          onClick: () => set(list.filter((x) => x !== v), true)
        }, "\xD7")
      ])));
      const results = h("div", { class: "stxc-results", role: "list" });
      const add = (raw) => {
        const items = String(raw).split(",").map((v) => v.trim()).filter(Boolean).filter((v) => list.indexOf(v) === -1);
        if (items.length) {
          set(list.concat(items), true);
        }
      };
      const input = h("input", {
        type: "text",
        id,
        "data-fk": id,
        disabled: readOnly,
        placeholder: isUsers ? field.type === "posts" ? L.searchPosts : L.searchUsers : field.placeholder || null,
        "aria-describedby": described,
        autocomplete: "off",
        onKeydown: (e) => {
          if (isUsers) {
            return;
          }
          if (e.key === "Enter" || e.key === ",") {
            e.preventDefault();
            add(e.target.value);
          } else if (e.key === "Backspace" && e.target.value === "" && list.length) {
            set(list.slice(0, -1), true);
          }
        },
        onBlur: (e) => {
          if (!isUsers && e.target.value.trim() !== "") {
            add(e.target.value);
          }
        },
        onInput: (e) => {
          if (!isUsers) {
            return;
          }
          const q = e.target.value.trim();
          results.textContent = "";
          if (q.length < 2) {
            return;
          }
          Promise.resolve(searchFn(q)).then((found) => {
            results.textContent = "";
            (found || []).forEach((u) => {
              state.userCache[String(u.value)] = u.label;
              results.appendChild(h("div", { role: "listitem" }, h("button", { type: "button", class: "stxc-result", onClick: () => {
                if (list.indexOf(String(u.value)) === -1) {
                  set(list.concat([String(u.value)]), true);
                }
              } }, u.label)));
            });
            if (!(found || []).length) {
              results.appendChild(h("p", { class: "stxc-help", role: "status" }, L.noUsers));
            }
          });
        }
      });
      const addBtn = isUsers || readOnly ? null : h("button", { type: "button", class: "stxc-btn stxc-btn--small", onClick: () => add(input.value) }, L.addValue);
      return h("div", { class: "stxc-field stxc-field--chips" }, [
        h("label", { class: "stxc-label", for: id }, field.label),
        chips,
        h("div", { class: "stxc-chip-input" }, [input, addBtn]),
        results,
        help
      ]);
    }
    function repeaterField(condition, field, value, readOnly, id, help) {
      const rows = Array.isArray(value) ? value : [];
      const setRows = (next, rerender) => setValue(condition, field.key, next, rerender);
      const rowNodes = rows.map((row, r) => {
        const rowValues = Object.assign({}, row);
        const rowCondition = { id: condition.id + "-r" + r, type: condition.type };
        return h("fieldset", { class: "stxc-row" }, [
          h("legend", { class: "stxc-label" }, sprintf(L.rowN, r + 1)),
          (field.fields || []).filter((f) => shown(f, rowValues, field.fields)).map((f) => fieldNode(rowCondition, f, rowValues, readOnly, (v, rerender) => {
            const next = rows.map((x) => Object.assign({}, x));
            next[r][f.key] = v;
            setRows(next, rerender);
          })),
          readOnly ? null : h("button", { type: "button", class: "stxc-btn stxc-btn--link", "data-fk": id + "-rm-" + r, onClick: () => setRows(rows.filter((x, i) => i !== r), true) }, sprintf(L.removeRow, r + 1))
        ]);
      });
      return h("div", { class: "stxc-field stxc-field--repeater" }, [
        h("p", { class: "stxc-label" }, field.label),
        rowNodes,
        readOnly ? null : h("button", { type: "button", class: "stxc-btn stxc-btn--small", "data-fk": id + "-add", onClick: () => {
          const blank = {};
          (field.fields || []).forEach((f) => {
            blank[f.key] = f.default !== void 0 ? f.default : "";
          });
          setRows(rows.concat([blank]), true);
        } }, L.addRow),
        help
      ]);
    }
    function conditionNode(condition, g, groupsCount) {
      const type = typeOf(registry, condition.type);
      const readOnly = !type || !!type.readonly;
      const open = !!state.open[condition.id];
      const bodyId = uid + "-body-" + condition.id;
      const text = conditionText(condition, registry, L, fmt);
      const values = Object.assign({}, defaults(registry, condition.type), condition.values);
      const head = h("div", { class: "stxc-cond-head", "data-head": condition.id }, [
        h("button", {
          type: "button",
          class: "stxc-cond-toggle",
          "aria-expanded": open ? "true" : "false",
          "aria-controls": bodyId,
          "data-fk": uid + "-t-" + condition.id,
          onClick: () => {
            state.open[condition.id] = !open;
            render2();
          }
        }, [
          h("span", { class: "stxc-cond-text" }, text),
          type && type.pro ? h("span", { class: "stxc-pro", role: "img", "aria-label": L.proFeature }, L.pro) : null
        ]),
        h("button", {
          type: "button",
          class: "stxc-icon stxc-remove",
          "data-fk": uid + "-x-" + condition.id,
          "aria-label": sprintf(L.removeCondition, text),
          title: L.remove,
          onClick: () => update((tree) => {
            const found = find(tree, condition.id);
            tree.groups[found.group].conditions.splice(found.index, 1);
            if (!tree.groups[found.group].conditions.length && tree.groups.length > 1) {
              tree.groups.splice(found.group, 1);
            }
          })
        }, "\xD7")
      ]);
      if (!open) {
        return h("div", { class: "stxc-cond", "data-id": condition.id }, head);
      }
      const fields = readOnly ? [h("p", { class: "stxc-help" }, L.readOnly)] : (type && type.fields || []).filter((f) => shown(f, values, type.fields)).map((f) => fieldNode(condition, f, values, false));
      const moveOptions = [h("option", { value: "" }, L.moveTo + "\u2026")];
      for (let i = 0; i < groupsCount; i++) {
        if (i !== g) {
          moveOptions.push(h("option", { value: String(i) }, sprintf(L.moveGroup, i + 1)));
        }
      }
      moveOptions.push(h("option", { value: "new" }, L.moveNew));
      const actions = h("div", { class: "stxc-cond-actions" }, [
        readOnly ? null : h("button", { type: "button", class: "stxc-btn stxc-btn--link", "data-fk": uid + "-d-" + condition.id, onClick: () => update((tree) => {
          const found = find(tree, condition.id);
          const copy = clone(found.condition);
          copy.id = makeId();
          tree.groups[found.group].conditions.splice(found.index + 1, 0, copy);
          state.open[copy.id] = true;
        }) }, L.duplicate),
        h("select", { class: "stxc-move", "aria-label": L.moveTo, "data-fk": uid + "-m-" + condition.id, onChange: (e) => {
          const target = e.target.value;
          if (target === "") {
            return;
          }
          update((tree) => {
            const found = find(tree, condition.id);
            const moved = tree.groups[found.group].conditions.splice(found.index, 1)[0];
            let dest = target === "new" ? -1 : Number(target);
            if (dest === -1) {
              tree.groups.push({ conditions: [] });
              dest = tree.groups.length - 1;
            }
            tree.groups[dest].conditions.push(moved);
            tree.groups = tree.groups.filter((grp, i) => grp.conditions.length || i === 0);
          });
        } }, moveOptions)
      ]);
      return h("div", { class: "stxc-cond is-open", "data-id": condition.id }, [
        head,
        h("div", { class: "stxc-cond-body", id: bodyId }, fields.concat([actions]))
      ]);
    }
    function addCondition(g, slug) {
      const type = typeOf(registry, slug);
      if (!type) {
        return;
      }
      if (type.locked) {
        state.lockedMessage = slug;
        render2();
        return;
      }
      const id = makeId();
      state.picker = null;
      state.query = "";
      state.lockedMessage = "";
      state.open[id] = true;
      update((tree) => {
        if (!tree.groups[g]) {
          tree.groups.push({ conditions: [] });
          g = tree.groups.length - 1;
        }
        tree.groups[g].conditions.push({ id, type: slug, values: Object.assign({}, type.initial || {}) });
      });
      focusLater('[data-fk="' + uid + "-t-" + id + '"]');
    }
    function applyPreset(preset) {
      state.picker = null;
      update((tree) => {
        tree.action = preset.action === "hide" ? "hide" : "show";
        tree.groups = (preset.groups || []).map((grp) => ({
          conditions: (grp.conditions || []).map((c) => ({ id: makeId(), type: c.type, values: Object.assign({}, c.values || {}) }))
        }));
        if (!tree.groups.length) {
          tree.groups = [{ conditions: [] }];
        }
      });
      focusLater(".stxc-summary");
    }
    function pickerNode(g) {
      const pickerId = uid + "-picker-" + g;
      const q = state.query.toLowerCase();
      const types = (registry.types || []).filter((t) => !t.hidden && !t.readonly);
      const matches = types.filter((t) => {
        if (state.category && t.category !== state.category) {
          return false;
        }
        if (!q) {
          return true;
        }
        return [t.label].concat(t.keywords || []).join(" ").toLowerCase().indexOf(q) !== -1;
      });
      const usedCategories = (registry.categories || []).filter((c) => types.some((t) => t.category === c.slug));
      const emptyRule = allConditions(state.tree).length === 0;
      const searchId = pickerId + "-search";
      const search = h("input", {
        type: "search",
        id: searchId,
        "data-fk": searchId,
        class: "stxc-search",
        value: state.query,
        placeholder: L.search,
        autocomplete: "off",
        onInput: (e) => {
          state.query = e.target.value;
          render2();
        }
      });
      const close = () => {
        state.picker = null;
        state.lockedMessage = "";
        render2();
        focusLater('[data-fk="' + uid + "-add-" + g + '"]');
      };
      const lockedType = state.lockedMessage ? typeOf(registry, state.lockedMessage) : null;
      return h("div", {
        class: "stxc-picker",
        id: pickerId,
        role: "group",
        "aria-label": L.addCondition,
        onKeydown: (e) => {
          if (e.key === "Escape") {
            e.stopPropagation();
            close();
          }
        }
      }, [
        h("div", { class: "stxc-picker-top" }, [
          h("label", { class: "screen-reader-text stxc-sr", for: searchId }, L.search),
          search,
          h("button", { type: "button", class: "stxc-icon", "aria-label": L.close, onClick: close }, "\xD7")
        ]),
        emptyRule && (registry.presets || []).length && !q ? h("div", { class: "stxc-presets" }, [
          h("p", { class: "stxc-label" }, L.presets),
          h("div", { class: "stxc-preset-list" }, registry.presets.map((p) => h("button", { type: "button", class: "stxc-preset", "data-preset": p.slug, onClick: () => applyPreset(p) }, [
            h("strong", null, p.label),
            p.description ? h("span", null, p.description) : null
          ])))
        ]) : null,
        h("div", { class: "stxc-cats", role: "group", "aria-label": L.search }, [{ slug: "", label: L.all }].concat(usedCategories).map((c) => h("button", {
          type: "button",
          class: "stxc-cat",
          "aria-pressed": state.category === c.slug ? "true" : "false",
          "data-fk": pickerId + "-cat-" + c.slug,
          onClick: () => {
            state.category = c.slug;
            render2();
          }
        }, c.label))),
        h("ul", { class: "stxc-options" }, matches.length ? matches.map((t) => h("li", null, h("button", {
          type: "button",
          class: "stxc-option" + (t.locked ? " is-locked" : ""),
          "data-type": t.slug,
          "data-fk": pickerId + "-o-" + t.slug,
          onClick: () => addCondition(g, t.slug)
        }, [
          h("span", null, t.label),
          t.pro ? h("span", { class: "stxc-pro", role: "img", "aria-label": L.proFeature }, L.pro) : null
        ]))) : h("li", { class: "stxc-help" }, L.noResults)),
        h("p", { class: "stxc-locked", role: "status" }, lockedType ? lockedType.lock && lockedType.lock.title ? [
          h("strong", null, lockedType.lock.title),
          lockedType.lock.text ? " " + lockedType.lock.text + " " : " ",
          (lockedType.lock.actions || []).map((a) => h("a", { href: a.url, target: "_blank", rel: "noopener" }, a.label))
        ] : [
          sprintf(L.lockedText, lockedType.label) + " ",
          opts.proUrl ? h("a", { href: opts.proUrl, target: "_blank", rel: "noopener" }, L.learnMore) : null
        ] : null)
      ]);
    }
    function groupNode(group, g, groups) {
      const legend = groups.length > 1 ? sprintf(L.groupN, g + 1) : L.allOf;
      const addId = uid + "-add-" + g;
      const pickerOpen = state.picker === g;
      return h("fieldset", { class: "stxc-group" }, [
        h("legend", { class: "stxc-legend" }, legend),
        groups.length > 1 ? h("button", {
          type: "button",
          class: "stxc-icon stxc-group-x",
          "aria-label": sprintf(L.removeGroup, g + 1),
          "data-fk": uid + "-gx-" + g,
          onClick: () => update((tree) => {
            tree.groups.splice(g, 1);
          })
        }, "\xD7") : null,
        group.conditions.map((c) => conditionNode(c, g, groups.length)),
        h("button", {
          type: "button",
          class: "stxc-btn stxc-add",
          "aria-expanded": pickerOpen ? "true" : "false",
          "aria-controls": uid + "-picker-" + g,
          "data-fk": addId,
          onClick: () => {
            state.picker = pickerOpen ? null : g;
            state.lockedMessage = "";
            render2();
            if (!pickerOpen) {
              focusLater("#" + uid + "-picker-" + g + "-search");
            }
          }
        }, "+ " + L.addCondition),
        pickerOpen ? pickerNode(g) : null
      ]);
    }
    function settingsNode() {
      const s = opts.settings;
      if (!s || !(s.fields || []).length) {
        return null;
      }
      const fake = { id: "settings", type: "" };
      const values = Object.assign({}, s.values || {});
      return h("div", { class: "stxc-settings" }, s.fields.filter((f) => shown(f, values, s.fields)).map((f) => fieldNode(fake, f, values, false, (v, rerender) => {
        s.values = Object.assign({}, s.values, { [f.key]: v });
        if (typeof s.onChange === "function") {
          s.onChange(f.key, v);
        }
        if (rerender !== false) {
          render2();
        }
      })));
    }
    let pendingFocus = null;
    function focusLater(selector) {
      pendingFocus = selector;
      setTimeout(() => {
        if (pendingFocus === selector) {
          const node = root.querySelector(selector);
          if (node) {
            node.focus();
          }
          pendingFocus = null;
        }
      }, 0);
    }
    function render2() {
      const active = document.activeElement && root.contains(document.activeElement) ? document.activeElement.getAttribute("data-fk") : null;
      const caret = active && document.activeElement.selectionStart !== void 0 ? document.activeElement.selectionStart : null;
      const tree = state.tree;
      const groups = tree.groups.length ? tree.groups : [{ conditions: [] }];
      const actionId = uid + "-action";
      const draftHelpId = uid + "-draft-help";
      root.textContent = "";
      root.appendChild(h("div", { class: "stxc-builder" + (tree.draft ? " is-draft" : "") }, [
        h("p", { class: "stxc-summary", "aria-live": "polite", tabindex: "-1" }, sentence(tree, registry, L, fmt)),
        renderWarnings(),
        settingsNode(),
        h("div", { class: "stxc-head" }, [
          h("label", { class: "stxc-sr", for: actionId }, L.actionLabel),
          // "Hide from everyone" is the tree's draft flag: the conditions stay (muted) and come back with Show or Hide.
          h("select", { id: actionId, "data-fk": actionId, class: "stxc-action", "aria-describedby": tree.draft ? draftHelpId : null, onChange: (e) => update((t) => {
            t.draft = e.target.value === "draft";
            if (!t.draft) {
              t.action = e.target.value;
            }
          }) }, [
            h("option", { value: "show", selected: !tree.draft && tree.action !== "hide" }, L.actionShow),
            h("option", { value: "hide", selected: !tree.draft && tree.action === "hide" }, L.actionHide),
            h("option", { value: "draft", selected: tree.draft }, L.actionDraft)
          ]),
          tree.draft ? null : h("span", null, " " + L.when)
        ]),
        tree.draft ? h("p", { class: "stxc-help stxc-draft-help", id: draftHelpId }, L.draftHelp) : null,
        h("div", { class: "stxc-groups" }, groups.reduce((nodes, group, g) => {
          if (g > 0) {
            nodes.push(h("div", { class: "stxc-or", "aria-hidden": "true" }, L.orLabel));
          }
          nodes.push(groupNode(group, g, groups));
          return nodes;
        }, [])),
        groups[groups.length - 1].conditions.length ? h("button", { type: "button", class: "stxc-btn stxc-add-group", "data-fk": uid + "-addgroup", onClick: () => {
          update((t) => {
            t.groups.push({ conditions: [] });
          });
          state.picker = state.tree.groups.length - 1;
          render2();
          focusLater("#" + uid + "-picker-" + state.picker + "-search");
        } }, "+ " + L.addGroup) : null
      ]));
      if (active && !pendingFocus) {
        const node = root.querySelector('[data-fk="' + active + '"]');
        if (node) {
          node.focus();
          if (caret !== null && node.setSelectionRange && node.type !== "checkbox" && node.tagName !== "SELECT") {
            try {
              node.setSelectionRange(caret, caret);
            } catch (e) {
            }
          }
        }
      }
    }
    render2();
    return {
      getValue: () => clone(state.tree),
      setValue(value) {
        state.tree = normalize(value) || make("show");
        render2();
      },
      setContext(context) {
        opts.context = context || {};
        refreshText();
      },
      setSettings(values) {
        if (opts.settings) {
          opts.settings.values = Object.assign({}, values);
          render2();
        }
      },
      cacheUsers(map) {
        Object.assign(state.userCache, map || {});
        render2();
      },
      destroy() {
        root.textContent = "";
        root.classList.remove("stxc");
      }
    };
  }

  // js/src/preview.js
  var previewLabels = {
    previewAs: "Preview as",
    me: "Me (no preview)",
    guest: "Guest",
    role: "Role: %s",
    user: "A specific user",
    userId: "User",
    device: "Device",
    anyDevice: "This device",
    desktop: "Desktop",
    tablet: "Tablet",
    mobile: "Mobile",
    time: "Date and time",
    timeHelp: "Empty: now (site time zone).",
    country: "Country",
    countryHelp: "Two-letter code, for example DE.",
    why: "Why",
    visible: "Shown for %s",
    hidden: "Hidden for %s",
    noRule: "No rule applies: shown.",
    draft: "Hidden from everyone.",
    met: "yes",
    notMet: "no",
    skipped: "does not apply here",
    group: "Group %d",
    matched: "The rule matches.",
    notMatched: "The rule does not match.",
    actionShow: "Action: show when it matches.",
    actionHide: "Action: hide when it matches.",
    checking: "Checking\u2026"
  };
  function h2(tag, attrs, children) {
    const node = document.createElement(tag);
    Object.keys(attrs || {}).forEach((key) => {
      const value = attrs[key];
      if (value === null || value === void 0 || value === false) {
        return;
      }
      if (key === "class") {
        node.className = value;
      } else if (key.indexOf("on") === 0 && typeof value === "function") {
        node.addEventListener(key.slice(2).toLowerCase(), value);
      } else if (key === "value") {
        node.value = value;
      } else if (key === "selected") {
        node.selected = !!value;
      } else {
        node.setAttribute(key, value === true ? "" : value);
      }
    });
    const add = (child) => {
      if (child === null || child === void 0 || child === false) {
        return;
      }
      if (Array.isArray(child)) {
        child.forEach(add);
        return;
      }
      node.appendChild(typeof child === "string" ? document.createTextNode(child) : child);
    };
    add(children);
    return node;
  }
  var count = 0;
  function contextLabel(ctx, options, labels) {
    const L = Object.assign({}, previewLabels, labels || {});
    if (!ctx || !ctx.as || ctx.as === "me") {
      return L.me;
    }
    let who = L.guest;
    if (ctx.as.indexOf("role:") === 0) {
      const role = (options.roles || []).find((r) => "role:" + r.value === ctx.as);
      who = sprintf(L.role, role ? role.label : ctx.as.slice(5));
    } else if (ctx.as === "user") {
      who = ctx.userLabel || (ctx.user ? "#" + ctx.user : L.user);
    }
    const extra = [];
    if (ctx.device) {
      extra.push(L[ctx.device] || ctx.device);
    }
    if (ctx.time) {
      extra.push(String(ctx.time).replace("T", " "));
    }
    if (ctx.country) {
      extra.push(String(ctx.country));
    }
    return extra.length ? who + " (" + extra.join(", ") + ")" : who;
  }
  function createPreviewBar(root, options) {
    const opts = Object.assign({ roles: [], users: false, value: {}, labels: {} }, options || {});
    const L = Object.assign({}, previewLabels, opts.labels);
    const uid = "stxp" + ++count;
    let ctx = Object.assign({ as: "me", user: "", device: "", time: "", country: "" }, opts.value || {});
    let results = [];
    const emit = () => typeof opts.onChange === "function" && opts.onChange(Object.assign({}, ctx));
    function render2() {
      const active = document.activeElement && root.contains(document.activeElement) ? document.activeElement.id : "";
      root.textContent = "";
      const asOptions = [h2("option", { value: "me", selected: ctx.as === "me" }, L.me), h2("option", { value: "guest", selected: ctx.as === "guest" }, L.guest)].concat(opts.roles.map((r) => h2("option", { value: "role:" + r.value, selected: ctx.as === "role:" + r.value }, sprintf(L.role, r.label)))).concat(opts.users ? [h2("option", { value: "user", selected: ctx.as === "user" }, L.user)] : []);
      root.appendChild(h2("div", { class: "stxc stxc-preview", role: "group", "aria-label": L.previewAs }, [
        h2("div", { class: "stxc-field" }, [
          h2("label", { class: "stxc-label", for: uid + "-as" }, L.previewAs),
          h2("select", { id: uid + "-as", onChange: (e) => {
            ctx.as = e.target.value;
            render2();
            emit();
          } }, asOptions)
        ]),
        ctx.as === "user" ? h2("div", { class: "stxc-field" }, [
          h2("label", { class: "stxc-label", for: uid + "-user" }, L.userId),
          h2("input", { type: "search", id: uid + "-user", value: ctx.userLabel || "", autocomplete: "off", onInput: (e) => {
            const q = e.target.value.trim();
            if (q.length < 2 || !opts.search) {
              results = [];
              renderResults();
              return;
            }
            Promise.resolve(opts.search(q)).then((found) => {
              results = found || [];
              renderResults();
            });
          } }),
          h2("div", { class: "stxc-results", id: uid + "-results" })
        ]) : null,
        h2("div", { class: "stxc-preview-row" }, [
          h2("div", { class: "stxc-field" }, [
            h2("label", { class: "stxc-label", for: uid + "-device" }, L.device),
            h2("select", { id: uid + "-device", onChange: (e) => {
              ctx.device = e.target.value;
              emit();
            } }, [["", L.anyDevice], ["desktop", L.desktop], ["tablet", L.tablet], ["mobile", L.mobile]].map((d) => h2("option", { value: d[0], selected: ctx.device === d[0] }, d[1])))
          ]),
          h2("div", { class: "stxc-field" }, [
            h2("label", { class: "stxc-label", for: uid + "-time" }, L.time),
            h2("input", { type: "datetime-local", id: uid + "-time", value: ctx.time || "", "aria-describedby": uid + "-time-help", onChange: (e) => {
              ctx.time = e.target.value;
              emit();
            } }),
            h2("p", { class: "stxc-help", id: uid + "-time-help" }, L.timeHelp)
          ])
        ]),
        opts.country ? h2("div", { class: "stxc-field" }, [
          h2("label", { class: "stxc-label", for: uid + "-country" }, L.country),
          h2("input", { type: "text", id: uid + "-country", value: ctx.country || "", maxlength: "2", size: "4", autocomplete: "off", "aria-describedby": uid + "-country-help", onChange: (e) => {
            ctx.country = e.target.value.trim().toUpperCase().slice(0, 2);
            emit();
          } }),
          h2("p", { class: "stxc-help", id: uid + "-country-help" }, L.countryHelp)
        ]) : null
      ]));
      renderResults();
      if (active) {
        const node = document.getElementById(active);
        if (node) {
          node.focus();
        }
      }
    }
    function renderResults() {
      const box = root.querySelector("#" + uid + "-results");
      if (!box) {
        return;
      }
      box.textContent = "";
      results.forEach((u) => box.appendChild(h2("button", { type: "button", class: "stxc-result", onClick: () => {
        ctx.user = String(u.value);
        ctx.userLabel = u.label;
        results = [];
        render2();
        emit();
      } }, u.label)));
    }
    render2();
    return {
      getValue: () => Object.assign({}, ctx),
      setValue(value) {
        ctx = Object.assign({ as: "me", user: "", device: "", time: "" }, value || {});
        render2();
      },
      destroy() {
        root.textContent = "";
      }
    };
  }
  function renderTrace(root, result, labels) {
    const L = Object.assign({}, previewLabels, labels || {});
    root.textContent = "";
    if (!result) {
      return;
    }
    const mark = (r) => r === true ? "\u2713" : r === false ? "\u2717" : "\u2013";
    const word = (r) => r === true ? L.met : r === false ? L.notMet : L.skipped;
    const head = result.reason === "draft" ? L.draft : result.reason === "no_conditions" ? L.noRule : sprintf(result.visible ? L.visible : L.hidden, result.who || "");
    const groups = result.groups || [];
    root.appendChild(h2("div", { class: "stxc stxc-trace" + (result.visible ? " is-visible" : " is-hidden"), role: "status" }, [
      h2("p", { class: "stxc-trace-head" }, head),
      groups.length ? h2("ol", { class: "stxc-trace-groups" }, groups.map((g, i) => h2("li", null, [
        groups.length > 1 ? h2("span", { class: "stxc-trace-group" }, sprintf(L.group, i + 1) + ": " + word(g.result)) : null,
        h2("ul", null, (g.conditions || []).map((c) => h2("li", { class: "stxc-trace-c is-" + (c.result === true ? "met" : c.result === false ? "not-met" : "skipped") }, [
          h2("span", { "aria-hidden": "true", class: "stxc-trace-mark" }, mark(c.result) + " "),
          c.text + ": " + word(c.result)
        ])))
      ]))) : null,
      result.reason === "matched" || result.reason === "not_matched" ? h2("p", { class: "stxc-help" }, (result.reason === "matched" ? L.matched : L.notMatched) + " " + (result.action === "hide" ? L.actionHide : L.actionShow)) : null
    ]));
  }

  // js/src/index.js
  var version = "1.0.0";
  return __toCommonJS(index_exports);
})();
