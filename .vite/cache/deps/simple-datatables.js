import {
  __publicField
} from "./chunk-L6OFPWCY.js";

// node_modules/simple-datatables/dist/module.js
var t = (t2) => "[object Object]" === Object.prototype.toString.call(t2);
var e = (e2) => {
  let s2 = false;
  try {
    s2 = JSON.parse(e2);
  } catch (t2) {
    return false;
  }
  return !(null === s2 || !Array.isArray(s2) && !t(s2)) && s2;
};
var s = (t2, e2) => {
  const s2 = document.createElement(t2);
  if (e2 && "object" == typeof e2) for (const t3 in e2) "html" === t3 ? s2.innerHTML = e2[t3] : s2.setAttribute(t3, e2[t3]);
  return s2;
};
var i = (t2) => ["#text", "#comment"].includes(t2.nodeName) ? t2.data : t2.childNodes ? t2.childNodes.map((t3) => i(t3)).join("") : "";
var n = (t2) => {
  if (null == t2) return "";
  if (t2.hasOwnProperty("text") || t2.hasOwnProperty("data")) {
    const e2 = t2;
    return e2.text ?? n(e2.data);
  }
  return t2.hasOwnProperty("nodeName") ? i(t2) : String(t2);
};
var a = function(t2) {
  return t2.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
};
var o = function(t2, e2) {
  let s2 = 0, i2 = 0;
  for (; s2 < t2 + 1; ) {
    e2[i2].hidden || (s2 += 1), i2 += 1;
  }
  return i2 - 1;
};
var r = function(t2) {
  const e2 = {};
  if (t2) for (const s2 of t2) e2[s2.name] = s2.value;
  return e2;
};
var l = (t2) => t2 ? t2.trim().split(" ").map((t3) => `.${t3}`).join("") : null;
var d = (t2, e2) => {
  const s2 = e2 == null ? void 0 : e2.split(" ").some((e3) => !t2.classList.contains(e3));
  return !s2;
};
var c = (t2, e2) => t2 ? e2 ? `${t2} ${e2}` : t2 : e2 || "";
var h = function(t2, e2 = 300) {
  let s2;
  return (...i2) => {
    clearTimeout(s2), s2 = window.setTimeout(() => t2(), e2);
  };
};
var u = function() {
  return u = Object.assign || function(t2) {
    for (var e2, s2 = arguments, i2 = 1, n2 = arguments.length; i2 < n2; i2++) for (var a2 in e2 = s2[i2]) Object.prototype.hasOwnProperty.call(e2, a2) && (t2[a2] = e2[a2]);
    return t2;
  }, u.apply(this, arguments);
};
function p(t2, e2, s2) {
  if (s2 || 2 === arguments.length) for (var i2, n2 = 0, a2 = e2.length; n2 < a2; n2++) !i2 && n2 in e2 || (i2 || (i2 = Array.prototype.slice.call(e2, 0, n2)), i2[n2] = e2[n2]);
  return t2.concat(i2 || Array.prototype.slice.call(e2));
}
var f = function() {
  function t2(t3) {
    void 0 === t3 && (t3 = {});
    var e2 = this;
    Object.entries(t3).forEach(function(t4) {
      var s2 = t4[0], i2 = t4[1];
      return e2[s2] = i2;
    });
  }
  return t2.prototype.toString = function() {
    return JSON.stringify(this);
  }, t2.prototype.setValue = function(t3, e2) {
    return this[t3] = e2, this;
  }, t2;
}();
function m(t2) {
  for (var e2 = arguments, s2 = [], i2 = 1; i2 < arguments.length; i2++) s2[i2 - 1] = e2[i2];
  return null != t2 && s2.some(function(e3) {
    var s3, i3;
    return "function" == typeof (null === (i3 = null === (s3 = null == t2 ? void 0 : t2.ownerDocument) || void 0 === s3 ? void 0 : s3.defaultView) || void 0 === i3 ? void 0 : i3[e3]) && t2 instanceof t2.ownerDocument.defaultView[e3];
  });
}
function g(t2, e2, s2) {
  var i2;
  return "#text" === t2.nodeName ? i2 = s2.document.createTextNode(t2.data) : "#comment" === t2.nodeName ? i2 = s2.document.createComment(t2.data) : (e2 ? (i2 = s2.document.createElementNS("http://www.w3.org/2000/svg", t2.nodeName), "foreignObject" === t2.nodeName && (e2 = false)) : "svg" === t2.nodeName.toLowerCase() ? (i2 = s2.document.createElementNS("http://www.w3.org/2000/svg", "svg"), e2 = true) : i2 = s2.document.createElement(t2.nodeName), t2.attributes && Object.entries(t2.attributes).forEach(function(t3) {
    var e3 = t3[0], s3 = t3[1];
    return i2.setAttribute(e3, s3);
  }), t2.childNodes && t2.childNodes.forEach(function(t3) {
    return i2.appendChild(g(t3, e2, s2));
  }), s2.valueDiffing && (t2.value && m(i2, "HTMLButtonElement", "HTMLDataElement", "HTMLInputElement", "HTMLLIElement", "HTMLMeterElement", "HTMLOptionElement", "HTMLProgressElement", "HTMLParamElement") && (i2.value = t2.value), t2.checked && m(i2, "HTMLInputElement") && (i2.checked = t2.checked), t2.selected && m(i2, "HTMLOptionElement") && (i2.selected = t2.selected))), i2;
}
var b = function(t2, e2) {
  for (e2 = e2.slice(); e2.length > 0; ) {
    var s2 = e2.splice(0, 1)[0];
    t2 = t2.childNodes[s2];
  }
  return t2;
};
function v(t2, e2, s2) {
  var i2, n2, a2, o2 = e2[s2._const.action], r2 = e2[s2._const.route];
  [s2._const.addElement, s2._const.addTextElement].includes(o2) || (i2 = b(t2, r2));
  var l2 = { diff: e2, node: i2 };
  if (s2.preDiffApply(l2)) return true;
  switch (o2) {
    case s2._const.addAttribute:
      if (!i2 || !m(i2, "Element")) return false;
      i2.setAttribute(e2[s2._const.name], e2[s2._const.value]);
      break;
    case s2._const.modifyAttribute:
      if (!i2 || !m(i2, "Element")) return false;
      i2.setAttribute(e2[s2._const.name], e2[s2._const.newValue]), m(i2, "HTMLInputElement") && "value" === e2[s2._const.name] && (i2.value = e2[s2._const.newValue]);
      break;
    case s2._const.removeAttribute:
      if (!i2 || !m(i2, "Element")) return false;
      i2.removeAttribute(e2[s2._const.name]);
      break;
    case s2._const.modifyTextElement:
      if (!i2 || !m(i2, "Text")) return false;
      s2.textDiff(i2, i2.data, e2[s2._const.oldValue], e2[s2._const.newValue]), m(i2.parentNode, "HTMLTextAreaElement") && (i2.parentNode.value = e2[s2._const.newValue]);
      break;
    case s2._const.modifyValue:
      if (!i2 || void 0 === i2.value) return false;
      i2.value = e2[s2._const.newValue];
      break;
    case s2._const.modifyComment:
      if (!i2 || !m(i2, "Comment")) return false;
      s2.textDiff(i2, i2.data, e2[s2._const.oldValue], e2[s2._const.newValue]);
      break;
    case s2._const.modifyChecked:
      if (!i2 || void 0 === i2.checked) return false;
      i2.checked = e2[s2._const.newValue];
      break;
    case s2._const.modifySelected:
      if (!i2 || void 0 === i2.selected) return false;
      i2.selected = e2[s2._const.newValue];
      break;
    case s2._const.replaceElement:
      var d2 = "svg" === e2[s2._const.newValue].nodeName.toLowerCase() || "http://www.w3.org/2000/svg" === i2.parentNode.namespaceURI;
      i2.parentNode.replaceChild(g(e2[s2._const.newValue], d2, s2), i2);
      break;
    case s2._const.relocateGroup:
      p([], new Array(e2[s2._const.groupLength]), true).map(function() {
        return i2.removeChild(i2.childNodes[e2[s2._const.from]]);
      }).forEach(function(t3, n3) {
        0 === n3 && (a2 = i2.childNodes[e2[s2._const.to]]), i2.insertBefore(t3, a2 || null);
      });
      break;
    case s2._const.removeElement:
      i2.parentNode.removeChild(i2);
      break;
    case s2._const.addElement:
      var c2 = (u2 = r2.slice()).splice(u2.length - 1, 1)[0];
      if (!m(i2 = b(t2, u2), "Element")) return false;
      i2.insertBefore(g(e2[s2._const.element], "http://www.w3.org/2000/svg" === i2.namespaceURI, s2), i2.childNodes[c2] || null);
      break;
    case s2._const.removeTextElement:
      if (!i2 || 3 !== i2.nodeType) return false;
      var h2 = i2.parentNode;
      h2.removeChild(i2), m(h2, "HTMLTextAreaElement") && (h2.value = "");
      break;
    case s2._const.addTextElement:
      var u2;
      c2 = (u2 = r2.slice()).splice(u2.length - 1, 1)[0];
      if (n2 = s2.document.createTextNode(e2[s2._const.value]), !(i2 = b(t2, u2)).childNodes) return false;
      i2.insertBefore(n2, i2.childNodes[c2] || null), m(i2.parentNode, "HTMLTextAreaElement") && (i2.parentNode.value = e2[s2._const.value]);
      break;
    default:
      console.log("unknown action");
  }
  return s2.postDiffApply({ diff: l2.diff, node: l2.node, newNode: n2 }), true;
}
function _(t2, e2, s2) {
  var i2 = t2[e2];
  t2[e2] = t2[s2], t2[s2] = i2;
}
function w(t2, e2, s2) {
  (e2 = e2.slice()).reverse(), e2.forEach(function(e3) {
    !function(t3, e4, s3) {
      switch (e4[s3._const.action]) {
        case s3._const.addAttribute:
          e4[s3._const.action] = s3._const.removeAttribute, v(t3, e4, s3);
          break;
        case s3._const.modifyAttribute:
          _(e4, s3._const.oldValue, s3._const.newValue), v(t3, e4, s3);
          break;
        case s3._const.removeAttribute:
          e4[s3._const.action] = s3._const.addAttribute, v(t3, e4, s3);
          break;
        case s3._const.modifyTextElement:
        case s3._const.modifyValue:
        case s3._const.modifyComment:
        case s3._const.modifyChecked:
        case s3._const.modifySelected:
        case s3._const.replaceElement:
          _(e4, s3._const.oldValue, s3._const.newValue), v(t3, e4, s3);
          break;
        case s3._const.relocateGroup:
          _(e4, s3._const.from, s3._const.to), v(t3, e4, s3);
          break;
        case s3._const.removeElement:
          e4[s3._const.action] = s3._const.addElement, v(t3, e4, s3);
          break;
        case s3._const.addElement:
          e4[s3._const.action] = s3._const.removeElement, v(t3, e4, s3);
          break;
        case s3._const.removeTextElement:
          e4[s3._const.action] = s3._const.addTextElement, v(t3, e4, s3);
          break;
        case s3._const.addTextElement:
          e4[s3._const.action] = s3._const.removeTextElement, v(t3, e4, s3);
          break;
        default:
          console.log("unknown action");
      }
    }(t2, e3, s2);
  });
}
var y = function(t2) {
  var e2 = [];
  return e2.push(t2.nodeName), "#text" !== t2.nodeName && "#comment" !== t2.nodeName && t2.attributes && (t2.attributes.class && e2.push("".concat(t2.nodeName, ".").concat(t2.attributes.class.replace(/ /g, "."))), t2.attributes.id && e2.push("".concat(t2.nodeName, "#").concat(t2.attributes.id))), e2;
};
var M = function(t2) {
  var e2 = {}, s2 = {};
  return t2.forEach(function(t3) {
    y(t3).forEach(function(t4) {
      var i2 = t4 in e2;
      i2 || t4 in s2 ? i2 && (delete e2[t4], s2[t4] = true) : e2[t4] = true;
    });
  }), e2;
};
var D = function(t2, e2) {
  var s2 = M(t2), i2 = M(e2), n2 = {};
  return Object.keys(s2).forEach(function(t3) {
    i2[t3] && (n2[t3] = true);
  }), n2;
};
var N = function(t2) {
  return delete t2.outerDone, delete t2.innerDone, delete t2.valueDone, !t2.childNodes || t2.childNodes.every(N);
};
var x = function(t2) {
  if (Object.prototype.hasOwnProperty.call(t2, "data")) return { nodeName: "#text" === t2.nodeName ? "#text" : "#comment", data: t2.data };
  var e2 = { nodeName: t2.nodeName };
  return Object.prototype.hasOwnProperty.call(t2, "attributes") && (e2.attributes = u({}, t2.attributes)), Object.prototype.hasOwnProperty.call(t2, "checked") && (e2.checked = t2.checked), Object.prototype.hasOwnProperty.call(t2, "value") && (e2.value = t2.value), Object.prototype.hasOwnProperty.call(t2, "selected") && (e2.selected = t2.selected), Object.prototype.hasOwnProperty.call(t2, "childNodes") && (e2.childNodes = t2.childNodes.map(function(t3) {
    return x(t3);
  })), e2;
};
var O = function(t2, e2) {
  if (!["nodeName", "value", "checked", "selected", "data"].every(function(s3) {
    return t2[s3] === e2[s3];
  })) return false;
  if (Object.prototype.hasOwnProperty.call(t2, "data")) return true;
  if (Boolean(t2.attributes) !== Boolean(e2.attributes)) return false;
  if (Boolean(t2.childNodes) !== Boolean(e2.childNodes)) return false;
  if (t2.attributes) {
    var s2 = Object.keys(t2.attributes), i2 = Object.keys(e2.attributes);
    if (s2.length !== i2.length) return false;
    if (!s2.every(function(s3) {
      return t2.attributes[s3] === e2.attributes[s3];
    })) return false;
  }
  if (t2.childNodes) {
    if (t2.childNodes.length !== e2.childNodes.length) return false;
    if (!t2.childNodes.every(function(t3, s3) {
      return O(t3, e2.childNodes[s3]);
    })) return false;
  }
  return true;
};
var E = function(t2, e2, s2, i2, n2) {
  if (void 0 === n2 && (n2 = false), !t2 || !e2) return false;
  if (t2.nodeName !== e2.nodeName) return false;
  if (["#text", "#comment"].includes(t2.nodeName)) return !!n2 || t2.data === e2.data;
  if (t2.nodeName in s2) return true;
  if (t2.attributes && e2.attributes) {
    if (t2.attributes.id) {
      if (t2.attributes.id !== e2.attributes.id) return false;
      if ("".concat(t2.nodeName, "#").concat(t2.attributes.id) in s2) return true;
    }
    if (t2.attributes.class && t2.attributes.class === e2.attributes.class) {
      if ("".concat(t2.nodeName, ".").concat(t2.attributes.class.replace(/ /g, ".")) in s2) return true;
    }
  }
  if (i2) return true;
  var a2 = t2.childNodes ? t2.childNodes.slice().reverse() : [], o2 = e2.childNodes ? e2.childNodes.slice().reverse() : [];
  if (a2.length !== o2.length) return false;
  if (n2) return a2.every(function(t3, e3) {
    return t3.nodeName === o2[e3].nodeName;
  });
  var r2 = D(a2, o2);
  return a2.every(function(t3, e3) {
    return E(t3, o2[e3], r2, true, true);
  });
};
var V = function(t2, e2) {
  return p([], new Array(t2), true).map(function() {
    return e2;
  });
};
var $ = function(t2, e2) {
  for (var s2 = t2.childNodes ? t2.childNodes : [], i2 = e2.childNodes ? e2.childNodes : [], n2 = V(s2.length, false), a2 = V(i2.length, false), o2 = [], r2 = function() {
    return arguments[1];
  }, l2 = false, d2 = function() {
    var t3 = function(t4, e3, s3, i3) {
      var n3 = 0, a3 = [], o3 = t4.length, r3 = e3.length, l3 = p([], new Array(o3 + 1), true).map(function() {
        return [];
      }), d3 = D(t4, e3), c2 = o3 === r3;
      c2 && t4.some(function(t5, s4) {
        var i4 = y(t5), n4 = y(e3[s4]);
        return i4.length !== n4.length ? (c2 = false, true) : (i4.some(function(t6, e4) {
          if (t6 !== n4[e4]) return c2 = false, true;
        }), !c2 || void 0);
      });
      for (var h2 = 0; h2 < o3; h2++) for (var u2 = t4[h2], f2 = 0; f2 < r3; f2++) {
        var m2 = e3[f2];
        s3[h2] || i3[f2] || !E(u2, m2, d3, c2) ? l3[h2 + 1][f2 + 1] = 0 : (l3[h2 + 1][f2 + 1] = l3[h2][f2] ? l3[h2][f2] + 1 : 1, l3[h2 + 1][f2 + 1] >= n3 && (n3 = l3[h2 + 1][f2 + 1], a3 = [h2 + 1, f2 + 1]));
      }
      return 0 !== n3 && { oldValue: a3[0] - n3, newValue: a3[1] - n3, length: n3 };
    }(s2, i2, n2, a2);
    t3 ? (o2.push(t3), p([], new Array(t3.length), true).map(r2).forEach(function(e3) {
      return function(t4, e4, s3, i3) {
        t4[s3.oldValue + i3] = true, e4[s3.newValue + i3] = true;
      }(n2, a2, t3, e3);
    })) : l2 = true;
  }; !l2; ) d2();
  return t2.subsets = o2, t2.subsetsAge = 100, o2;
};
var C = function() {
  function t2() {
    this.list = [];
  }
  return t2.prototype.add = function(t3) {
    var e2;
    (e2 = this.list).push.apply(e2, t3);
  }, t2.prototype.forEach = function(t3) {
    this.list.forEach(function(e2) {
      return t3(e2);
    });
  }, t2;
}();
function k(t2, e2) {
  var s2, i2, n2 = t2;
  for (e2 = e2.slice(); e2.length > 0; ) i2 = e2.splice(0, 1)[0], s2 = n2, n2 = n2.childNodes ? n2.childNodes[i2] : void 0;
  return { node: n2, parentNode: s2, nodeIndex: i2 };
}
function S(t2, e2, s2) {
  return e2.forEach(function(e3) {
    !function(t3, e4, s3) {
      var i2, n2, a2, o2;
      if (![s3._const.addElement, s3._const.addTextElement].includes(e4[s3._const.action])) {
        var r2 = k(t3, e4[s3._const.route]);
        n2 = r2.node, a2 = r2.parentNode, o2 = r2.nodeIndex;
      }
      var l2, d2, c2 = [], h2 = { diff: e4, node: n2 };
      if (s3.preVirtualDiffApply(h2)) return true;
      switch (e4[s3._const.action]) {
        case s3._const.addAttribute:
          n2.attributes || (n2.attributes = {}), n2.attributes[e4[s3._const.name]] = e4[s3._const.value], "checked" === e4[s3._const.name] ? n2.checked = true : "selected" === e4[s3._const.name] ? n2.selected = true : "INPUT" === n2.nodeName && "value" === e4[s3._const.name] && (n2.value = e4[s3._const.value]);
          break;
        case s3._const.modifyAttribute:
          n2.attributes[e4[s3._const.name]] = e4[s3._const.newValue];
          break;
        case s3._const.removeAttribute:
          delete n2.attributes[e4[s3._const.name]], 0 === Object.keys(n2.attributes).length && delete n2.attributes, "checked" === e4[s3._const.name] ? n2.checked = false : "selected" === e4[s3._const.name] ? delete n2.selected : "INPUT" === n2.nodeName && "value" === e4[s3._const.name] && delete n2.value;
          break;
        case s3._const.modifyTextElement:
          n2.data = e4[s3._const.newValue], "TEXTAREA" === a2.nodeName && (a2.value = e4[s3._const.newValue]);
          break;
        case s3._const.modifyValue:
          n2.value = e4[s3._const.newValue];
          break;
        case s3._const.modifyComment:
          n2.data = e4[s3._const.newValue];
          break;
        case s3._const.modifyChecked:
          n2.checked = e4[s3._const.newValue];
          break;
        case s3._const.modifySelected:
          n2.selected = e4[s3._const.newValue];
          break;
        case s3._const.replaceElement:
          l2 = x(e4[s3._const.newValue]), a2.childNodes[o2] = l2;
          break;
        case s3._const.relocateGroup:
          n2.childNodes.splice(e4[s3._const.from], e4[s3._const.groupLength]).reverse().forEach(function(t4) {
            return n2.childNodes.splice(e4[s3._const.to], 0, t4);
          }), n2.subsets && n2.subsets.forEach(function(t4) {
            if (e4[s3._const.from] < e4[s3._const.to] && t4.oldValue <= e4[s3._const.to] && t4.oldValue > e4[s3._const.from]) t4.oldValue -= e4[s3._const.groupLength], (i3 = t4.oldValue + t4.length - e4[s3._const.to]) > 0 && (c2.push({ oldValue: e4[s3._const.to] + e4[s3._const.groupLength], newValue: t4.newValue + t4.length - i3, length: i3 }), t4.length -= i3);
            else if (e4[s3._const.from] > e4[s3._const.to] && t4.oldValue > e4[s3._const.to] && t4.oldValue < e4[s3._const.from]) {
              var i3;
              t4.oldValue += e4[s3._const.groupLength], (i3 = t4.oldValue + t4.length - e4[s3._const.to]) > 0 && (c2.push({ oldValue: e4[s3._const.to] + e4[s3._const.groupLength], newValue: t4.newValue + t4.length - i3, length: i3 }), t4.length -= i3);
            } else t4.oldValue === e4[s3._const.from] && (t4.oldValue = e4[s3._const.to]);
          });
          break;
        case s3._const.removeElement:
          a2.childNodes.splice(o2, 1), a2.subsets && a2.subsets.forEach(function(t4) {
            t4.oldValue > o2 ? t4.oldValue -= 1 : t4.oldValue === o2 ? t4.delete = true : t4.oldValue < o2 && t4.oldValue + t4.length > o2 && (t4.oldValue + t4.length - 1 === o2 ? t4.length-- : (c2.push({ newValue: t4.newValue + o2 - t4.oldValue, oldValue: o2, length: t4.length - o2 + t4.oldValue - 1 }), t4.length = o2 - t4.oldValue));
          }), n2 = a2;
          break;
        case s3._const.addElement:
          var u2 = (d2 = e4[s3._const.route].slice()).splice(d2.length - 1, 1)[0];
          n2 = null === (i2 = k(t3, d2)) || void 0 === i2 ? void 0 : i2.node, l2 = x(e4[s3._const.element]), n2.childNodes || (n2.childNodes = []), u2 >= n2.childNodes.length ? n2.childNodes.push(l2) : n2.childNodes.splice(u2, 0, l2), n2.subsets && n2.subsets.forEach(function(t4) {
            if (t4.oldValue >= u2) t4.oldValue += 1;
            else if (t4.oldValue < u2 && t4.oldValue + t4.length > u2) {
              var e5 = t4.oldValue + t4.length - u2;
              c2.push({ newValue: t4.newValue + t4.length - e5, oldValue: u2 + 1, length: e5 }), t4.length -= e5;
            }
          });
          break;
        case s3._const.removeTextElement:
          a2.childNodes.splice(o2, 1), "TEXTAREA" === a2.nodeName && delete a2.value, a2.subsets && a2.subsets.forEach(function(t4) {
            t4.oldValue > o2 ? t4.oldValue -= 1 : t4.oldValue === o2 ? t4.delete = true : t4.oldValue < o2 && t4.oldValue + t4.length > o2 && (t4.oldValue + t4.length - 1 === o2 ? t4.length-- : (c2.push({ newValue: t4.newValue + o2 - t4.oldValue, oldValue: o2, length: t4.length - o2 + t4.oldValue - 1 }), t4.length = o2 - t4.oldValue));
          }), n2 = a2;
          break;
        case s3._const.addTextElement:
          var p2 = (d2 = e4[s3._const.route].slice()).splice(d2.length - 1, 1)[0];
          l2 = { nodeName: "#text", data: e4[s3._const.value] }, (n2 = k(t3, d2).node).childNodes || (n2.childNodes = []), p2 >= n2.childNodes.length ? n2.childNodes.push(l2) : n2.childNodes.splice(p2, 0, l2), "TEXTAREA" === n2.nodeName && (n2.value = e4[s3._const.newValue]), n2.subsets && n2.subsets.forEach(function(t4) {
            if (t4.oldValue >= p2 && (t4.oldValue += 1), t4.oldValue < p2 && t4.oldValue + t4.length > p2) {
              var e5 = t4.oldValue + t4.length - p2;
              c2.push({ newValue: t4.newValue + t4.length - e5, oldValue: p2 + 1, length: e5 }), t4.length -= e5;
            }
          });
          break;
        default:
          console.log("unknown action");
      }
      n2.subsets && (n2.subsets = n2.subsets.filter(function(t4) {
        return !t4.delete && t4.oldValue !== t4.newValue;
      }), c2.length && (n2.subsets = n2.subsets.concat(c2))), s3.postVirtualDiffApply({ node: h2.node, diff: h2.diff, newNode: l2 });
    }(t2, e3, s2);
  }), true;
}
function T(t2, e2) {
  void 0 === e2 && (e2 = { valueDiffing: true });
  var s2 = { nodeName: t2.nodeName };
  if (m(t2, "Text", "Comment")) s2.data = t2.data;
  else {
    if (t2.attributes && t2.attributes.length > 0) s2.attributes = {}, Array.prototype.slice.call(t2.attributes).forEach(function(t3) {
      return s2.attributes[t3.name] = t3.value;
    });
    if (t2.childNodes && t2.childNodes.length > 0) s2.childNodes = [], Array.prototype.slice.call(t2.childNodes).forEach(function(t3) {
      return s2.childNodes.push(T(t3, e2));
    });
    e2.valueDiffing && (m(t2, "HTMLTextAreaElement") && (s2.value = t2.value), m(t2, "HTMLInputElement") && ["radio", "checkbox"].includes(t2.type.toLowerCase()) && void 0 !== t2.checked ? s2.checked = t2.checked : m(t2, "HTMLButtonElement", "HTMLDataElement", "HTMLInputElement", "HTMLLIElement", "HTMLMeterElement", "HTMLOptionElement", "HTMLProgressElement", "HTMLParamElement") && (s2.value = t2.value), m(t2, "HTMLOptionElement") && (s2.selected = t2.selected));
  }
  return s2;
}
var A = /<\s*\/*[a-zA-Z:_][a-zA-Z0-9:_\-.]*\s*(?:"[^"]*"['"]*|'[^']*'['"]*|[^'"/>])*\/*\s*>|<!--(?:.|\n|\r)*?-->/g;
var L = /\s([^'"/\s><]+?)[\s/>]|([^\s=]+)=\s?(".*?"|'.*?')/g;
function P(t2) {
  return t2.replace(/&lt;/g, "<").replace(/&gt;/g, ">").replace(/&amp;/g, "&");
}
var R = { area: true, base: true, br: true, col: true, embed: true, hr: true, img: true, input: true, keygen: true, link: true, menuItem: true, meta: true, param: true, source: true, track: true, wbr: true };
var H = function(t2, e2) {
  var s2 = { nodeName: "", attributes: {} }, i2 = false, n2 = t2.match(/<\/?([^\s]+?)[/\s>]/);
  if (n2 && (s2.nodeName = e2 || "svg" === n2[1] ? n2[1] : n2[1].toUpperCase(), (R[n2[1]] || "/" === t2.charAt(t2.length - 2)) && (i2 = true), s2.nodeName.startsWith("!--"))) {
    var a2 = t2.indexOf("-->");
    return { type: "comment", node: { nodeName: "#comment", data: -1 !== a2 ? t2.slice(4, a2) : "" }, voidElement: i2 };
  }
  for (var o2 = new RegExp(L), r2 = null, l2 = false; !l2; ) if (null === (r2 = o2.exec(t2))) l2 = true;
  else if (r2[0].trim()) if (r2[1]) {
    var d2 = r2[1].trim(), c2 = [d2, ""];
    d2.indexOf("=") > -1 && (c2 = d2.split("=")), s2.attributes[c2[0]] = c2[1], o2.lastIndex--;
  } else r2[2] && (s2.attributes[r2[2]] = r2[3].trim().substring(1, r2[3].length - 1));
  return { type: "tag", node: s2, voidElement: i2 };
};
var I = function(t2, e2) {
  void 0 === e2 && (e2 = { valueDiffing: true, caseSensitive: false });
  var s2, i2 = [], n2 = -1, a2 = [], o2 = false;
  if (0 !== t2.indexOf("<")) {
    var r2 = t2.indexOf("<");
    i2.push({ nodeName: "#text", data: -1 === r2 ? t2 : t2.substring(0, r2) });
  }
  return t2.replace(A, function(r3, l2) {
    var d2 = "/" !== r3.charAt(1), c2 = r3.startsWith("<!--"), h2 = l2 + r3.length, u2 = t2.charAt(h2);
    if (c2) {
      var p2 = H(r3, e2.caseSensitive).node;
      if (n2 < 0) return i2.push(p2), "";
      var f2 = a2[n2];
      return f2 && p2.nodeName && (f2.node.childNodes || (f2.node.childNodes = []), f2.node.childNodes.push(p2)), "";
    }
    if (d2) {
      if ("svg" === (s2 = H(r3, e2.caseSensitive || o2)).node.nodeName && (o2 = true), n2++, !s2.voidElement && u2 && "<" !== u2) {
        s2.node.childNodes || (s2.node.childNodes = []);
        var m2 = P(t2.slice(h2, t2.indexOf("<", h2)));
        s2.node.childNodes.push({ nodeName: "#text", data: m2 }), e2.valueDiffing && "TEXTAREA" === s2.node.nodeName && (s2.node.value = m2);
      }
      0 === n2 && s2.node.nodeName && i2.push(s2.node);
      var g2 = a2[n2 - 1];
      g2 && s2.node.nodeName && (g2.node.childNodes || (g2.node.childNodes = []), g2.node.childNodes.push(s2.node)), a2[n2] = s2;
    }
    if ((!d2 || s2.voidElement) && (n2 > -1 && (s2.voidElement || e2.caseSensitive && s2.node.nodeName === r3.slice(2, -1) || !e2.caseSensitive && s2.node.nodeName.toUpperCase() === r3.slice(2, -1).toUpperCase()) && --n2 > -1 && ("svg" === s2.node.nodeName && (o2 = false), s2 = a2[n2]), "<" !== u2 && u2)) {
      var b2 = -1 === n2 ? i2 : a2[n2].node.childNodes || [], v2 = t2.indexOf("<", h2);
      m2 = P(t2.slice(h2, -1 === v2 ? void 0 : v2));
      b2.push({ nodeName: "#text", data: m2 });
    }
    return "";
  }), i2[0];
};
var Y = function() {
  function t2(t3, e2, s2) {
    this.options = s2, this.t1 = "undefined" != typeof Element && m(t3, "Element") ? T(t3, this.options) : "string" == typeof t3 ? I(t3, this.options) : JSON.parse(JSON.stringify(t3)), this.t2 = "undefined" != typeof Element && m(e2, "Element") ? T(e2, this.options) : "string" == typeof e2 ? I(e2, this.options) : JSON.parse(JSON.stringify(e2)), this.diffcount = 0, this.foundAll = false, this.debug && (this.t1Orig = "undefined" != typeof Element && m(t3, "Element") ? T(t3, this.options) : "string" == typeof t3 ? I(t3, this.options) : JSON.parse(JSON.stringify(t3)), this.t2Orig = "undefined" != typeof Element && m(e2, "Element") ? T(e2, this.options) : "string" == typeof e2 ? I(e2, this.options) : JSON.parse(JSON.stringify(e2))), this.tracker = new C();
  }
  return t2.prototype.init = function() {
    return this.findDiffs(this.t1, this.t2);
  }, t2.prototype.findDiffs = function(t3, e2) {
    var s2;
    do {
      if (this.options.debug && (this.diffcount += 1, this.diffcount > this.options.diffcap)) throw new Error("surpassed diffcap:".concat(JSON.stringify(this.t1Orig), " -> ").concat(JSON.stringify(this.t2Orig)));
      0 === (s2 = this.findNextDiff(t3, e2, [])).length && (O(t3, e2) || (this.foundAll ? console.error("Could not find remaining diffs!") : (this.foundAll = true, N(t3), s2 = this.findNextDiff(t3, e2, [])))), s2.length > 0 && (this.foundAll = false, this.tracker.add(s2), S(t3, s2, this.options));
    } while (s2.length > 0);
    return this.tracker.list;
  }, t2.prototype.findNextDiff = function(t3, e2, s2) {
    var i2, n2;
    if (this.options.maxDepth && s2.length > this.options.maxDepth) return [];
    if (!t3.outerDone) {
      if (i2 = this.findOuterDiff(t3, e2, s2), this.options.filterOuterDiff && (n2 = this.options.filterOuterDiff(t3, e2, i2)) && (i2 = n2), i2.length > 0) return t3.outerDone = true, i2;
      t3.outerDone = true;
    }
    if (Object.prototype.hasOwnProperty.call(t3, "data")) return [];
    if (!t3.innerDone) {
      if ((i2 = this.findInnerDiff(t3, e2, s2)).length > 0) return i2;
      t3.innerDone = true;
    }
    if (this.options.valueDiffing && !t3.valueDone) {
      if ((i2 = this.findValueDiff(t3, e2, s2)).length > 0) return t3.valueDone = true, i2;
      t3.valueDone = true;
    }
    return [];
  }, t2.prototype.findOuterDiff = function(t3, e2, s2) {
    var i2, n2, a2, o2, r2, l2, d2 = [];
    if (t3.nodeName !== e2.nodeName) {
      if (!s2.length) throw new Error("Top level nodes have to be of the same kind.");
      return [new f().setValue(this.options._const.action, this.options._const.replaceElement).setValue(this.options._const.oldValue, x(t3)).setValue(this.options._const.newValue, x(e2)).setValue(this.options._const.route, s2)];
    }
    if (s2.length && this.options.diffcap < Math.abs((t3.childNodes || []).length - (e2.childNodes || []).length)) return [new f().setValue(this.options._const.action, this.options._const.replaceElement).setValue(this.options._const.oldValue, x(t3)).setValue(this.options._const.newValue, x(e2)).setValue(this.options._const.route, s2)];
    if (Object.prototype.hasOwnProperty.call(t3, "data") && t3.data !== e2.data) return "#text" === t3.nodeName ? [new f().setValue(this.options._const.action, this.options._const.modifyTextElement).setValue(this.options._const.route, s2).setValue(this.options._const.oldValue, t3.data).setValue(this.options._const.newValue, e2.data)] : [new f().setValue(this.options._const.action, this.options._const.modifyComment).setValue(this.options._const.route, s2).setValue(this.options._const.oldValue, t3.data).setValue(this.options._const.newValue, e2.data)];
    for (n2 = t3.attributes ? Object.keys(t3.attributes).sort() : [], a2 = e2.attributes ? Object.keys(e2.attributes).sort() : [], o2 = n2.length, l2 = 0; l2 < o2; l2++) i2 = n2[l2], -1 === (r2 = a2.indexOf(i2)) ? d2.push(new f().setValue(this.options._const.action, this.options._const.removeAttribute).setValue(this.options._const.route, s2).setValue(this.options._const.name, i2).setValue(this.options._const.value, t3.attributes[i2])) : (a2.splice(r2, 1), t3.attributes[i2] !== e2.attributes[i2] && d2.push(new f().setValue(this.options._const.action, this.options._const.modifyAttribute).setValue(this.options._const.route, s2).setValue(this.options._const.name, i2).setValue(this.options._const.oldValue, t3.attributes[i2]).setValue(this.options._const.newValue, e2.attributes[i2])));
    for (o2 = a2.length, l2 = 0; l2 < o2; l2++) i2 = a2[l2], d2.push(new f().setValue(this.options._const.action, this.options._const.addAttribute).setValue(this.options._const.route, s2).setValue(this.options._const.name, i2).setValue(this.options._const.value, e2.attributes[i2]));
    return d2;
  }, t2.prototype.findInnerDiff = function(t3, e2, s2) {
    var i2 = t3.childNodes ? t3.childNodes.slice() : [], n2 = e2.childNodes ? e2.childNodes.slice() : [], a2 = Math.max(i2.length, n2.length), o2 = Math.abs(i2.length - n2.length), r2 = [], l2 = 0;
    if (!this.options.maxChildCount || a2 < this.options.maxChildCount) {
      var d2 = Boolean(t3.subsets && t3.subsetsAge--), c2 = d2 ? t3.subsets : t3.childNodes && e2.childNodes ? $(t3, e2) : [];
      if (c2.length > 0 && (r2 = this.attemptGroupRelocation(t3, e2, c2, s2, d2)).length > 0) return r2;
    }
    for (var h2 = 0; h2 < a2; h2 += 1) {
      var u2 = i2[h2], p2 = n2[h2];
      o2 && (u2 && !p2 ? "#text" === u2.nodeName ? (r2.push(new f().setValue(this.options._const.action, this.options._const.removeTextElement).setValue(this.options._const.route, s2.concat(l2)).setValue(this.options._const.value, u2.data)), l2 -= 1) : (r2.push(new f().setValue(this.options._const.action, this.options._const.removeElement).setValue(this.options._const.route, s2.concat(l2)).setValue(this.options._const.element, x(u2))), l2 -= 1) : p2 && !u2 && ("#text" === p2.nodeName ? r2.push(new f().setValue(this.options._const.action, this.options._const.addTextElement).setValue(this.options._const.route, s2.concat(l2)).setValue(this.options._const.value, p2.data)) : r2.push(new f().setValue(this.options._const.action, this.options._const.addElement).setValue(this.options._const.route, s2.concat(l2)).setValue(this.options._const.element, x(p2))))), u2 && p2 && (!this.options.maxChildCount || a2 < this.options.maxChildCount ? r2 = r2.concat(this.findNextDiff(u2, p2, s2.concat(l2))) : O(u2, p2) || (i2.length > n2.length ? ("#text" === u2.nodeName ? r2.push(new f().setValue(this.options._const.action, this.options._const.removeTextElement).setValue(this.options._const.route, s2.concat(l2)).setValue(this.options._const.value, u2.data)) : r2.push(new f().setValue(this.options._const.action, this.options._const.removeElement).setValue(this.options._const.element, x(u2)).setValue(this.options._const.route, s2.concat(l2))), i2.splice(h2, 1), h2 -= 1, l2 -= 1, o2 -= 1) : i2.length < n2.length ? (r2 = r2.concat([new f().setValue(this.options._const.action, this.options._const.addElement).setValue(this.options._const.element, x(p2)).setValue(this.options._const.route, s2.concat(l2))]), i2.splice(h2, 0, x(p2)), o2 -= 1) : r2 = r2.concat([new f().setValue(this.options._const.action, this.options._const.replaceElement).setValue(this.options._const.oldValue, x(u2)).setValue(this.options._const.newValue, x(p2)).setValue(this.options._const.route, s2.concat(l2))]))), l2 += 1;
    }
    return t3.innerDone = true, r2;
  }, t2.prototype.attemptGroupRelocation = function(t3, e2, s2, i2, n2) {
    for (var a2, o2, r2, l2, d2, c2 = function(t4, e3, s3) {
      var i3 = t4.childNodes ? V(t4.childNodes.length, true) : [], n3 = e3.childNodes ? V(e3.childNodes.length, true) : [], a3 = 0;
      return s3.forEach(function(t5) {
        for (var e4 = t5.oldValue + t5.length, s4 = t5.newValue + t5.length, o3 = t5.oldValue; o3 < e4; o3 += 1) i3[o3] = a3;
        for (o3 = t5.newValue; o3 < s4; o3 += 1) n3[o3] = a3;
        a3 += 1;
      }), { gaps1: i3, gaps2: n3 };
    }(t3, e2, s2), h2 = c2.gaps1, u2 = c2.gaps2, p2 = t3.childNodes.slice(), m2 = e2.childNodes.slice(), g2 = Math.min(h2.length, u2.length), b2 = [], v2 = 0, _2 = 0; v2 < g2; _2 += 1, v2 += 1) if (!n2 || true !== h2[v2] && true !== u2[v2]) {
      if (true === h2[_2]) if ("#text" === (l2 = p2[_2]).nodeName) if ("#text" === m2[v2].nodeName) {
        if (l2.data !== m2[v2].data) {
          for (var w2 = _2; p2.length > w2 + 1 && "#text" === p2[w2 + 1].nodeName; ) if (w2 += 1, m2[v2].data === p2[w2].data) {
            d2 = true;
            break;
          }
          d2 || b2.push(new f().setValue(this.options._const.action, this.options._const.modifyTextElement).setValue(this.options._const.route, i2.concat(_2)).setValue(this.options._const.oldValue, l2.data).setValue(this.options._const.newValue, m2[v2].data));
        }
      } else b2.push(new f().setValue(this.options._const.action, this.options._const.removeTextElement).setValue(this.options._const.route, i2.concat(_2)).setValue(this.options._const.value, l2.data)), h2.splice(_2, 1), p2.splice(_2, 1), g2 = Math.min(h2.length, u2.length), _2 -= 1, v2 -= 1;
      else true === u2[v2] ? b2.push(new f().setValue(this.options._const.action, this.options._const.replaceElement).setValue(this.options._const.oldValue, x(l2)).setValue(this.options._const.newValue, x(m2[v2])).setValue(this.options._const.route, i2.concat(_2))) : (b2.push(new f().setValue(this.options._const.action, this.options._const.removeElement).setValue(this.options._const.route, i2.concat(_2)).setValue(this.options._const.element, x(l2))), h2.splice(_2, 1), p2.splice(_2, 1), g2 = Math.min(h2.length, u2.length), _2 -= 1, v2 -= 1);
      else if (true === u2[v2]) "#text" === (l2 = m2[v2]).nodeName ? (b2.push(new f().setValue(this.options._const.action, this.options._const.addTextElement).setValue(this.options._const.route, i2.concat(_2)).setValue(this.options._const.value, l2.data)), h2.splice(_2, 0, true), p2.splice(_2, 0, { nodeName: "#text", data: l2.data }), g2 = Math.min(h2.length, u2.length)) : (b2.push(new f().setValue(this.options._const.action, this.options._const.addElement).setValue(this.options._const.route, i2.concat(_2)).setValue(this.options._const.element, x(l2))), h2.splice(_2, 0, true), p2.splice(_2, 0, x(l2)), g2 = Math.min(h2.length, u2.length));
      else if (h2[_2] !== u2[v2]) {
        if (b2.length > 0) return b2;
        if (r2 = s2[h2[_2]], (o2 = Math.min(r2.newValue, p2.length - r2.length)) !== r2.oldValue) {
          a2 = false;
          for (var y2 = 0; y2 < r2.length; y2 += 1) E(p2[o2 + y2], p2[r2.oldValue + y2], {}, false, true) || (a2 = true);
          if (a2) return [new f().setValue(this.options._const.action, this.options._const.relocateGroup).setValue(this.options._const.groupLength, r2.length).setValue(this.options._const.from, r2.oldValue).setValue(this.options._const.to, o2).setValue(this.options._const.route, i2)];
        }
      }
    } else ;
    return b2;
  }, t2.prototype.findValueDiff = function(t3, e2, s2) {
    var i2 = [];
    return t3.selected !== e2.selected && i2.push(new f().setValue(this.options._const.action, this.options._const.modifySelected).setValue(this.options._const.oldValue, t3.selected).setValue(this.options._const.newValue, e2.selected).setValue(this.options._const.route, s2)), (t3.value || e2.value) && t3.value !== e2.value && "OPTION" !== t3.nodeName && i2.push(new f().setValue(this.options._const.action, this.options._const.modifyValue).setValue(this.options._const.oldValue, t3.value || "").setValue(this.options._const.newValue, e2.value || "").setValue(this.options._const.route, s2)), t3.checked !== e2.checked && i2.push(new f().setValue(this.options._const.action, this.options._const.modifyChecked).setValue(this.options._const.oldValue, t3.checked).setValue(this.options._const.newValue, e2.checked).setValue(this.options._const.route, s2)), i2;
  }, t2;
}();
var j = { debug: false, diffcap: 10, maxDepth: false, maxChildCount: 50, valueDiffing: true, textDiff: function(t2, e2, s2, i2) {
  t2.data = i2;
}, preVirtualDiffApply: function() {
}, postVirtualDiffApply: function() {
}, preDiffApply: function() {
}, postDiffApply: function() {
}, filterOuterDiff: null, compress: false, _const: false, document: !("undefined" == typeof window || !window.document) && window.document, components: [] };
var q = function() {
  function t2(t3) {
    if (void 0 === t3 && (t3 = {}), Object.entries(j).forEach(function(e3) {
      var s3 = e3[0], i2 = e3[1];
      Object.prototype.hasOwnProperty.call(t3, s3) || (t3[s3] = i2);
    }), !t3._const) {
      var e2 = ["addAttribute", "modifyAttribute", "removeAttribute", "modifyTextElement", "relocateGroup", "removeElement", "addElement", "removeTextElement", "addTextElement", "replaceElement", "modifyValue", "modifyChecked", "modifySelected", "modifyComment", "action", "route", "oldValue", "newValue", "element", "group", "groupLength", "from", "to", "name", "value", "data", "attributes", "nodeName", "childNodes", "checked", "selected"], s2 = {};
      t3.compress ? e2.forEach(function(t4, e3) {
        return s2[t4] = e3;
      }) : e2.forEach(function(t4) {
        return s2[t4] = t4;
      }), t3._const = s2;
    }
    this.options = t3;
  }
  return t2.prototype.apply = function(t3, e2) {
    return function(t4, e3, s2) {
      return e3.every(function(e4) {
        return v(t4, e4, s2);
      });
    }(t3, e2, this.options);
  }, t2.prototype.undo = function(t3, e2) {
    return w(t3, e2, this.options);
  }, t2.prototype.diff = function(t3, e2) {
    return new Y(t3, e2, this.options).init();
  }, t2;
}();
var F = (t2, e2, s2, { classes: i2, format: n2, hiddenHeader: a2, sortable: o2, scrollY: r2, type: l2 }, { noColumnWidths: d2, unhideHeader: h2 }) => ({ nodeName: "TR", childNodes: t2.map((t3, u2) => {
  const p2 = e2[u2] || { type: l2, format: n2, sortable: true, searchable: true };
  if (p2.hidden) return;
  const f2 = t3.attributes ? { ...t3.attributes } : {};
  if (p2.sortable && o2 && (!r2.length || h2) && (p2.filter ? f2["data-filterable"] = "true" : f2["data-sortable"] = "true"), p2.headerClass && (f2.class = c(f2.class, p2.headerClass)), s2.sort && s2.sort.column === u2) {
    const t4 = "asc" === s2.sort.dir ? i2.ascending : i2.descending;
    f2.class = c(f2.class, t4), f2["aria-sort"] = "asc" === s2.sort.dir ? "ascending" : "descending";
  } else s2.filters[u2] && (f2.class = c(f2.class, i2.filterActive));
  if (s2.widths[u2] && !d2) {
    const t4 = `width: ${s2.widths[u2]}%;`;
    f2.style = c(f2.style, t4);
  }
  if (r2.length && !h2) {
    const t4 = "padding-bottom: 0;padding-top: 0;border: 0;";
    f2.style = c(f2.style, t4);
  }
  const m2 = "html" === t3.type ? t3.data : [{ nodeName: "#text", data: t3.text ?? String(t3.data) }];
  return { nodeName: "TH", attributes: f2, childNodes: !a2 && !r2.length || h2 ? p2.sortable && o2 ? [{ nodeName: "BUTTON", attributes: { class: p2.filter ? i2.filter : i2.sorter }, childNodes: m2 }] : m2 : [{ nodeName: "#text", data: "" }] };
}).filter((t3) => t3) });
var B = (t2, e2, s2, i2, a2, o2, { classes: r2, hiddenHeader: l2, header: d2, footer: h2, format: u2, sortable: p2, scrollY: f2, type: m2, rowRender: g2, tabIndex: b2 }, { noColumnWidths: v2, unhideHeader: _2, renderHeader: w2 }, y2, M2) => {
  const D2 = { nodeName: "TABLE", attributes: { ...t2 }, childNodes: [{ nodeName: "TBODY", childNodes: s2.map(({ row: t3, index: e3 }) => {
    const s3 = { nodeName: "TR", attributes: { ...t3.attributes, "data-index": String(e3) }, childNodes: t3.cells.map((t4, s4) => {
      const o3 = i2[s4] || { type: m2, format: u2, sortable: true, searchable: true };
      if (o3.hidden) return;
      const r3 = { nodeName: "TD", attributes: t4.attributes ? { ...t4.attributes } : {}, childNodes: "html" === o3.type ? t4.data : [{ nodeName: "#text", data: n(t4) }] };
      if (d2 || h2 || !a2.widths[s4] || v2 || (r3.attributes.style = c(r3.attributes.style, `width: ${a2.widths[s4]}%;`)), o3.cellClass && (r3.attributes.class = c(r3.attributes.class, o3.cellClass)), o3.render) {
        const i3 = o3.render(t4.data, r3, e3, s4);
        if (i3) {
          if ("string" != typeof i3) return i3;
          {
            const t5 = I(`<td>${i3}</td>`);
            1 === t5.childNodes.length && ["#text", "#comment"].includes(t5.childNodes[0].nodeName) ? r3.childNodes[0].data = i3 : r3.childNodes = t5.childNodes;
          }
        }
      }
      return r3;
    }).filter((t4) => t4) };
    if (e3 === o2 && (s3.attributes.class = c(s3.attributes.class, r2.cursor)), g2) {
      const i3 = g2(t3, s3, e3);
      if (i3) {
        if ("string" != typeof i3) return i3;
        {
          const t4 = I(`<tr>${i3}</tr>`);
          !t4.childNodes || 1 === t4.childNodes.length && ["#text", "#comment"].includes(t4.childNodes[0].nodeName) ? s3.childNodes[0].data = i3 : s3.childNodes = t4.childNodes;
        }
      }
    }
    return s3;
  }) }] };
  if (D2.attributes.class = c(D2.attributes.class, r2.table), d2 || h2 || w2) {
    const t3 = F(e2, i2, a2, { classes: r2, hiddenHeader: l2, sortable: p2, scrollY: f2 }, { noColumnWidths: v2, unhideHeader: _2 });
    if (d2 || w2) {
      const e3 = { nodeName: "THEAD", childNodes: [t3] };
      !f2.length && !l2 || _2 || (e3.attributes = { style: "height: 0px;" }), D2.childNodes.unshift(e3);
    }
    if (h2) {
      const e3 = { nodeName: "TFOOT", childNodes: [d2 ? structuredClone(t3) : t3] };
      !f2.length && !l2 || _2 || (e3.attributes = { style: "height: 0px;" }), D2.childNodes.push(e3);
    }
  }
  return y2.forEach((t3) => D2.childNodes.push(t3)), M2.forEach((t3) => D2.childNodes.push(t3)), false !== b2 && (D2.attributes.tabindex = String(b2)), D2;
};
function z(t2) {
  return t2 && t2.__esModule && Object.prototype.hasOwnProperty.call(t2, "default") ? t2.default : t2;
}
var U = { exports: {} };
var W = z(U.exports = function() {
  var t2 = 1e3, e2 = 6e4, s2 = 36e5, i2 = "millisecond", n2 = "second", a2 = "minute", o2 = "hour", r2 = "day", l2 = "week", d2 = "month", c2 = "quarter", h2 = "year", u2 = "date", p2 = "Invalid Date", f2 = /^(\d{4})[-/]?(\d{1,2})?[-/]?(\d{0,2})[Tt\s]*(\d{1,2})?:?(\d{1,2})?:?(\d{1,2})?[.:]?(\d+)?$/, m2 = /\[([^\]]+)]|Y{1,4}|M{1,4}|D{1,2}|d{1,4}|H{1,2}|h{1,2}|a|A|m{1,2}|s{1,2}|Z{1,2}|SSS/g, g2 = { name: "en", weekdays: "Sunday_Monday_Tuesday_Wednesday_Thursday_Friday_Saturday".split("_"), months: "January_February_March_April_May_June_July_August_September_October_November_December".split("_"), ordinal: function(t3) {
    var e3 = ["th", "st", "nd", "rd"], s3 = t3 % 100;
    return "[" + t3 + (e3[(s3 - 20) % 10] || e3[s3] || e3[0]) + "]";
  } }, b2 = function(t3, e3, s3) {
    var i3 = String(t3);
    return !i3 || i3.length >= e3 ? t3 : "" + Array(e3 + 1 - i3.length).join(s3) + t3;
  }, v2 = { s: b2, z: function(t3) {
    var e3 = -t3.utcOffset(), s3 = Math.abs(e3), i3 = Math.floor(s3 / 60), n3 = s3 % 60;
    return (e3 <= 0 ? "+" : "-") + b2(i3, 2, "0") + ":" + b2(n3, 2, "0");
  }, m: function t3(e3, s3) {
    if (e3.date() < s3.date()) return -t3(s3, e3);
    var i3 = 12 * (s3.year() - e3.year()) + (s3.month() - e3.month()), n3 = e3.clone().add(i3, d2), a3 = s3 - n3 < 0, o3 = e3.clone().add(i3 + (a3 ? -1 : 1), d2);
    return +(-(i3 + (s3 - n3) / (a3 ? n3 - o3 : o3 - n3)) || 0);
  }, a: function(t3) {
    return t3 < 0 ? Math.ceil(t3) || 0 : Math.floor(t3);
  }, p: function(t3) {
    return { M: d2, y: h2, w: l2, d: r2, D: u2, h: o2, m: a2, s: n2, ms: i2, Q: c2 }[t3] || String(t3 || "").toLowerCase().replace(/s$/, "");
  }, u: function(t3) {
    return void 0 === t3;
  } }, _2 = "en", w2 = {};
  w2[_2] = g2;
  var y2 = "$isDayjsObject", M2 = function(t3) {
    return t3 instanceof O2 || !(!t3 || !t3[y2]);
  }, D2 = function t3(e3, s3, i3) {
    var n3;
    if (!e3) return _2;
    if ("string" == typeof e3) {
      var a3 = e3.toLowerCase();
      w2[a3] && (n3 = a3), s3 && (w2[a3] = s3, n3 = a3);
      var o3 = e3.split("-");
      if (!n3 && o3.length > 1) return t3(o3[0]);
    } else {
      var r3 = e3.name;
      w2[r3] = e3, n3 = r3;
    }
    return !i3 && n3 && (_2 = n3), n3 || !i3 && _2;
  }, N2 = function(t3, e3) {
    if (M2(t3)) return t3.clone();
    var s3 = "object" == typeof e3 ? e3 : {};
    return s3.date = t3, s3.args = arguments, new O2(s3);
  }, x2 = v2;
  x2.l = D2, x2.i = M2, x2.w = function(t3, e3) {
    return N2(t3, { locale: e3.$L, utc: e3.$u, x: e3.$x, $offset: e3.$offset });
  };
  var O2 = function() {
    function g3(t3) {
      this.$L = D2(t3.locale, null, true), this.parse(t3), this.$x = this.$x || t3.x || {}, this[y2] = true;
    }
    var b3 = g3.prototype;
    return b3.parse = function(t3) {
      this.$d = function(t4) {
        var e3 = t4.date, s3 = t4.utc;
        if (null === e3) return /* @__PURE__ */ new Date(NaN);
        if (x2.u(e3)) return /* @__PURE__ */ new Date();
        if (e3 instanceof Date) return new Date(e3);
        if ("string" == typeof e3 && !/Z$/i.test(e3)) {
          var i3 = e3.match(f2);
          if (i3) {
            var n3 = i3[2] - 1 || 0, a3 = (i3[7] || "0").substring(0, 3);
            return s3 ? new Date(Date.UTC(i3[1], n3, i3[3] || 1, i3[4] || 0, i3[5] || 0, i3[6] || 0, a3)) : new Date(i3[1], n3, i3[3] || 1, i3[4] || 0, i3[5] || 0, i3[6] || 0, a3);
          }
        }
        return new Date(e3);
      }(t3), this.init();
    }, b3.init = function() {
      var t3 = this.$d;
      this.$y = t3.getFullYear(), this.$M = t3.getMonth(), this.$D = t3.getDate(), this.$W = t3.getDay(), this.$H = t3.getHours(), this.$m = t3.getMinutes(), this.$s = t3.getSeconds(), this.$ms = t3.getMilliseconds();
    }, b3.$utils = function() {
      return x2;
    }, b3.isValid = function() {
      return !(this.$d.toString() === p2);
    }, b3.isSame = function(t3, e3) {
      var s3 = N2(t3);
      return this.startOf(e3) <= s3 && s3 <= this.endOf(e3);
    }, b3.isAfter = function(t3, e3) {
      return N2(t3) < this.startOf(e3);
    }, b3.isBefore = function(t3, e3) {
      return this.endOf(e3) < N2(t3);
    }, b3.$g = function(t3, e3, s3) {
      return x2.u(t3) ? this[e3] : this.set(s3, t3);
    }, b3.unix = function() {
      return Math.floor(this.valueOf() / 1e3);
    }, b3.valueOf = function() {
      return this.$d.getTime();
    }, b3.startOf = function(t3, e3) {
      var s3 = this, i3 = !!x2.u(e3) || e3, c3 = x2.p(t3), p3 = function(t4, e4) {
        var n3 = x2.w(s3.$u ? Date.UTC(s3.$y, e4, t4) : new Date(s3.$y, e4, t4), s3);
        return i3 ? n3 : n3.endOf(r2);
      }, f3 = function(t4, e4) {
        return x2.w(s3.toDate()[t4].apply(s3.toDate("s"), (i3 ? [0, 0, 0, 0] : [23, 59, 59, 999]).slice(e4)), s3);
      }, m3 = this.$W, g4 = this.$M, b4 = this.$D, v3 = "set" + (this.$u ? "UTC" : "");
      switch (c3) {
        case h2:
          return i3 ? p3(1, 0) : p3(31, 11);
        case d2:
          return i3 ? p3(1, g4) : p3(0, g4 + 1);
        case l2:
          var _3 = this.$locale().weekStart || 0, w3 = (m3 < _3 ? m3 + 7 : m3) - _3;
          return p3(i3 ? b4 - w3 : b4 + (6 - w3), g4);
        case r2:
        case u2:
          return f3(v3 + "Hours", 0);
        case o2:
          return f3(v3 + "Minutes", 1);
        case a2:
          return f3(v3 + "Seconds", 2);
        case n2:
          return f3(v3 + "Milliseconds", 3);
        default:
          return this.clone();
      }
    }, b3.endOf = function(t3) {
      return this.startOf(t3, false);
    }, b3.$set = function(t3, e3) {
      var s3, l3 = x2.p(t3), c3 = "set" + (this.$u ? "UTC" : ""), p3 = (s3 = {}, s3[r2] = c3 + "Date", s3[u2] = c3 + "Date", s3[d2] = c3 + "Month", s3[h2] = c3 + "FullYear", s3[o2] = c3 + "Hours", s3[a2] = c3 + "Minutes", s3[n2] = c3 + "Seconds", s3[i2] = c3 + "Milliseconds", s3)[l3], f3 = l3 === r2 ? this.$D + (e3 - this.$W) : e3;
      if (l3 === d2 || l3 === h2) {
        var m3 = this.clone().set(u2, 1);
        m3.$d[p3](f3), m3.init(), this.$d = m3.set(u2, Math.min(this.$D, m3.daysInMonth())).$d;
      } else p3 && this.$d[p3](f3);
      return this.init(), this;
    }, b3.set = function(t3, e3) {
      return this.clone().$set(t3, e3);
    }, b3.get = function(t3) {
      return this[x2.p(t3)]();
    }, b3.add = function(i3, c3) {
      var u3, p3 = this;
      i3 = Number(i3);
      var f3 = x2.p(c3), m3 = function(t3) {
        var e3 = N2(p3);
        return x2.w(e3.date(e3.date() + Math.round(t3 * i3)), p3);
      };
      if (f3 === d2) return this.set(d2, this.$M + i3);
      if (f3 === h2) return this.set(h2, this.$y + i3);
      if (f3 === r2) return m3(1);
      if (f3 === l2) return m3(7);
      var g4 = (u3 = {}, u3[a2] = e2, u3[o2] = s2, u3[n2] = t2, u3)[f3] || 1, b4 = this.$d.getTime() + i3 * g4;
      return x2.w(b4, this);
    }, b3.subtract = function(t3, e3) {
      return this.add(-1 * t3, e3);
    }, b3.format = function(t3) {
      var e3 = this, s3 = this.$locale();
      if (!this.isValid()) return s3.invalidDate || p2;
      var i3 = t3 || "YYYY-MM-DDTHH:mm:ssZ", n3 = x2.z(this), a3 = this.$H, o3 = this.$m, r3 = this.$M, l3 = s3.weekdays, d3 = s3.months, c3 = s3.meridiem, h3 = function(t4, s4, n4, a4) {
        return t4 && (t4[s4] || t4(e3, i3)) || n4[s4].slice(0, a4);
      }, u3 = function(t4) {
        return x2.s(a3 % 12 || 12, t4, "0");
      }, f3 = c3 || function(t4, e4, s4) {
        var i4 = t4 < 12 ? "AM" : "PM";
        return s4 ? i4.toLowerCase() : i4;
      };
      return i3.replace(m2, function(t4, i4) {
        return i4 || function(t5) {
          switch (t5) {
            case "YY":
              return String(e3.$y).slice(-2);
            case "YYYY":
              return x2.s(e3.$y, 4, "0");
            case "M":
              return r3 + 1;
            case "MM":
              return x2.s(r3 + 1, 2, "0");
            case "MMM":
              return h3(s3.monthsShort, r3, d3, 3);
            case "MMMM":
              return h3(d3, r3);
            case "D":
              return e3.$D;
            case "DD":
              return x2.s(e3.$D, 2, "0");
            case "d":
              return String(e3.$W);
            case "dd":
              return h3(s3.weekdaysMin, e3.$W, l3, 2);
            case "ddd":
              return h3(s3.weekdaysShort, e3.$W, l3, 3);
            case "dddd":
              return l3[e3.$W];
            case "H":
              return String(a3);
            case "HH":
              return x2.s(a3, 2, "0");
            case "h":
              return u3(1);
            case "hh":
              return u3(2);
            case "a":
              return f3(a3, o3, true);
            case "A":
              return f3(a3, o3, false);
            case "m":
              return String(o3);
            case "mm":
              return x2.s(o3, 2, "0");
            case "s":
              return String(e3.$s);
            case "ss":
              return x2.s(e3.$s, 2, "0");
            case "SSS":
              return x2.s(e3.$ms, 3, "0");
            case "Z":
              return n3;
          }
          return null;
        }(t4) || n3.replace(":", "");
      });
    }, b3.utcOffset = function() {
      return 15 * -Math.round(this.$d.getTimezoneOffset() / 15);
    }, b3.diff = function(i3, u3, p3) {
      var f3, m3 = this, g4 = x2.p(u3), b4 = N2(i3), v3 = (b4.utcOffset() - this.utcOffset()) * e2, _3 = this - b4, w3 = function() {
        return x2.m(m3, b4);
      };
      switch (g4) {
        case h2:
          f3 = w3() / 12;
          break;
        case d2:
          f3 = w3();
          break;
        case c2:
          f3 = w3() / 3;
          break;
        case l2:
          f3 = (_3 - v3) / 6048e5;
          break;
        case r2:
          f3 = (_3 - v3) / 864e5;
          break;
        case o2:
          f3 = _3 / s2;
          break;
        case a2:
          f3 = _3 / e2;
          break;
        case n2:
          f3 = _3 / t2;
          break;
        default:
          f3 = _3;
      }
      return p3 ? f3 : x2.a(f3);
    }, b3.daysInMonth = function() {
      return this.endOf(d2).$D;
    }, b3.$locale = function() {
      return w2[this.$L];
    }, b3.locale = function(t3, e3) {
      if (!t3) return this.$L;
      var s3 = this.clone(), i3 = D2(t3, e3, true);
      return i3 && (s3.$L = i3), s3;
    }, b3.clone = function() {
      return x2.w(this.$d, this);
    }, b3.toDate = function() {
      return new Date(this.valueOf());
    }, b3.toJSON = function() {
      return this.isValid() ? this.toISOString() : null;
    }, b3.toISOString = function() {
      return this.$d.toISOString();
    }, b3.toString = function() {
      return this.$d.toUTCString();
    }, g3;
  }(), E2 = O2.prototype;
  return N2.prototype = E2, [["$ms", i2], ["$s", n2], ["$m", a2], ["$H", o2], ["$W", r2], ["$M", d2], ["$y", h2], ["$D", u2]].forEach(function(t3) {
    E2[t3[1]] = function(e3) {
      return this.$g(e3, t3[0], t3[1]);
    };
  }), N2.extend = function(t3, e3) {
    return t3.$i || (t3(e3, O2, N2), t3.$i = true), N2;
  }, N2.locale = D2, N2.isDayjs = M2, N2.unix = function(t3) {
    return N2(1e3 * t3);
  }, N2.en = w2[_2], N2.Ls = w2, N2.p = {}, N2;
}());
var J = { exports: {} };
var Q = z(J.exports = function() {
  var t2 = { LTS: "h:mm:ss A", LT: "h:mm A", L: "MM/DD/YYYY", LL: "MMMM D, YYYY", LLL: "MMMM D, YYYY h:mm A", LLLL: "dddd, MMMM D, YYYY h:mm A" }, e2 = /(\[[^[]*\])|([-_:/.,()\s]+)|(A|a|YYYY|YY?|MM?M?M?|Do|DD?|hh?|HH?|mm?|ss?|S{1,3}|z|ZZ?)/g, s2 = /\d\d/, i2 = /\d\d?/, n2 = /\d*[^-_:/,()\s\d]+/, a2 = {}, o2 = function(t3) {
    return (t3 = +t3) + (t3 > 68 ? 1900 : 2e3);
  }, r2 = function(t3) {
    return function(e3) {
      this[t3] = +e3;
    };
  }, l2 = [/[+-]\d\d:?(\d\d)?|Z/, function(t3) {
    (this.zone || (this.zone = {})).offset = function(t4) {
      if (!t4) return 0;
      if ("Z" === t4) return 0;
      var e3 = t4.match(/([+-]|\d\d)/g), s3 = 60 * e3[1] + (+e3[2] || 0);
      return 0 === s3 ? 0 : "+" === e3[0] ? -s3 : s3;
    }(t3);
  }], d2 = function(t3) {
    var e3 = a2[t3];
    return e3 && (e3.indexOf ? e3 : e3.s.concat(e3.f));
  }, c2 = function(t3, e3) {
    var s3, i3 = a2.meridiem;
    if (i3) {
      for (var n3 = 1; n3 <= 24; n3 += 1) if (t3.indexOf(i3(n3, 0, e3)) > -1) {
        s3 = n3 > 12;
        break;
      }
    } else s3 = t3 === (e3 ? "pm" : "PM");
    return s3;
  }, h2 = { A: [n2, function(t3) {
    this.afternoon = c2(t3, false);
  }], a: [n2, function(t3) {
    this.afternoon = c2(t3, true);
  }], S: [/\d/, function(t3) {
    this.milliseconds = 100 * +t3;
  }], SS: [s2, function(t3) {
    this.milliseconds = 10 * +t3;
  }], SSS: [/\d{3}/, function(t3) {
    this.milliseconds = +t3;
  }], s: [i2, r2("seconds")], ss: [i2, r2("seconds")], m: [i2, r2("minutes")], mm: [i2, r2("minutes")], H: [i2, r2("hours")], h: [i2, r2("hours")], HH: [i2, r2("hours")], hh: [i2, r2("hours")], D: [i2, r2("day")], DD: [s2, r2("day")], Do: [n2, function(t3) {
    var e3 = a2.ordinal, s3 = t3.match(/\d+/);
    if (this.day = s3[0], e3) for (var i3 = 1; i3 <= 31; i3 += 1) e3(i3).replace(/\[|\]/g, "") === t3 && (this.day = i3);
  }], M: [i2, r2("month")], MM: [s2, r2("month")], MMM: [n2, function(t3) {
    var e3 = d2("months"), s3 = (d2("monthsShort") || e3.map(function(t4) {
      return t4.slice(0, 3);
    })).indexOf(t3) + 1;
    if (s3 < 1) throw new Error();
    this.month = s3 % 12 || s3;
  }], MMMM: [n2, function(t3) {
    var e3 = d2("months").indexOf(t3) + 1;
    if (e3 < 1) throw new Error();
    this.month = e3 % 12 || e3;
  }], Y: [/[+-]?\d+/, r2("year")], YY: [s2, function(t3) {
    this.year = o2(t3);
  }], YYYY: [/\d{4}/, r2("year")], Z: l2, ZZ: l2 };
  function u2(s3) {
    var i3, n3;
    i3 = s3, n3 = a2 && a2.formats;
    for (var o3 = (s3 = i3.replace(/(\[[^\]]+])|(LTS?|l{1,4}|L{1,4})/g, function(e3, s4, i4) {
      var a3 = i4 && i4.toUpperCase();
      return s4 || n3[i4] || t2[i4] || n3[a3].replace(/(\[[^\]]+])|(MMMM|MM|DD|dddd)/g, function(t3, e4, s5) {
        return e4 || s5.slice(1);
      });
    })).match(e2), r3 = o3.length, l3 = 0; l3 < r3; l3 += 1) {
      var d3 = o3[l3], c3 = h2[d3], u3 = c3 && c3[0], p2 = c3 && c3[1];
      o3[l3] = p2 ? { regex: u3, parser: p2 } : d3.replace(/^\[|\]$/g, "");
    }
    return function(t3) {
      for (var e3 = {}, s4 = 0, i4 = 0; s4 < r3; s4 += 1) {
        var n4 = o3[s4];
        if ("string" == typeof n4) i4 += n4.length;
        else {
          var a3 = n4.regex, l4 = n4.parser, d4 = t3.slice(i4), c4 = a3.exec(d4)[0];
          l4.call(e3, c4), t3 = t3.replace(c4, "");
        }
      }
      return function(t4) {
        var e4 = t4.afternoon;
        if (void 0 !== e4) {
          var s5 = t4.hours;
          e4 ? s5 < 12 && (t4.hours += 12) : 12 === s5 && (t4.hours = 0), delete t4.afternoon;
        }
      }(e3), e3;
    };
  }
  return function(t3, e3, s3) {
    s3.p.customParseFormat = true, t3 && t3.parseTwoDigitYear && (o2 = t3.parseTwoDigitYear);
    var i3 = e3.prototype, n3 = i3.parse;
    i3.parse = function(t4) {
      var e4 = t4.date, i4 = t4.utc, o3 = t4.args;
      this.$u = i4;
      var r3 = o3[1];
      if ("string" == typeof r3) {
        var l3 = true === o3[2], d3 = true === o3[3], c3 = l3 || d3, h3 = o3[2];
        d3 && (h3 = o3[2]), a2 = this.$locale(), !l3 && h3 && (a2 = s3.Ls[h3]), this.$d = function(t5, e5, s4) {
          try {
            if (["x", "X"].indexOf(e5) > -1) return new Date(("X" === e5 ? 1e3 : 1) * t5);
            var i5 = u2(e5)(t5), n4 = i5.year, a3 = i5.month, o4 = i5.day, r4 = i5.hours, l4 = i5.minutes, d4 = i5.seconds, c4 = i5.milliseconds, h4 = i5.zone, p3 = /* @__PURE__ */ new Date(), f3 = o4 || (n4 || a3 ? 1 : p3.getDate()), m3 = n4 || p3.getFullYear(), g2 = 0;
            n4 && !a3 || (g2 = a3 > 0 ? a3 - 1 : p3.getMonth());
            var b2 = r4 || 0, v2 = l4 || 0, _2 = d4 || 0, w2 = c4 || 0;
            return h4 ? new Date(Date.UTC(m3, g2, f3, b2, v2, _2, w2 + 60 * h4.offset * 1e3)) : s4 ? new Date(Date.UTC(m3, g2, f3, b2, v2, _2, w2)) : new Date(m3, g2, f3, b2, v2, _2, w2);
          } catch (t6) {
            return /* @__PURE__ */ new Date("");
          }
        }(e4, r3, i4), this.init(), h3 && true !== h3 && (this.$L = this.locale(h3).$L), c3 && e4 != this.format(r3) && (this.$d = /* @__PURE__ */ new Date("")), a2 = {};
      } else if (r3 instanceof Array) for (var p2 = r3.length, f2 = 1; f2 <= p2; f2 += 1) {
        o3[1] = r3[f2 - 1];
        var m2 = s3.apply(this, o3);
        if (m2.isValid()) {
          this.$d = m2.$d, this.$L = m2.$L, this.init();
          break;
        }
        f2 === p2 && (this.$d = /* @__PURE__ */ new Date(""));
      }
      else n3.call(this, t4);
    };
  };
}());
W.extend(Q);
var X = (t2, e2) => {
  let s2;
  if (e2) switch (e2) {
    case "ISO_8601":
      s2 = t2;
      break;
    case "RFC_2822":
      s2 = W(t2.slice(5), "DD MMM YYYY HH:mm:ss ZZ").unix();
      break;
    case "MYSQL":
      s2 = W(t2, "YYYY-MM-DD hh:mm:ss").unix();
      break;
    case "UNIX":
      s2 = W(t2).unix();
      break;
    default:
      s2 = W(t2, e2, true).valueOf();
  }
  return s2;
};
var Z = (t2, e2) => {
  if ((t2 == null ? void 0 : t2.constructor) === Object && Object.prototype.hasOwnProperty.call(t2, "data") && !Object.keys(t2).find((t3) => !["text", "order", "data", "attributes"].includes(t3))) return t2;
  const s2 = { data: t2 };
  switch (e2.type) {
    case "string":
      "string" != typeof t2 && (s2.text = String(s2.data), s2.order = s2.text);
      break;
    case "date":
      e2.format && (s2.order = X(String(s2.data), e2.format));
      break;
    case "number":
      s2.text = String(s2.data), s2.data = parseFloat(s2.data), s2.order = s2.data;
      break;
    case "html": {
      const t3 = Array.isArray(s2.data) ? { nodeName: "TD", childNodes: s2.data } : I(`<td>${String(s2.data)}</td>`);
      s2.data = t3.childNodes || [];
      const e3 = i(t3);
      s2.text = e3, s2.order = e3;
      break;
    }
    case "boolean":
      "string" == typeof s2.data && (s2.data = s2.data.toLowerCase().trim()), s2.data = !["false", false, null, void 0, 0].includes(s2.data), s2.order = s2.data ? 1 : 0, s2.text = String(s2.data);
      break;
    case "other":
      s2.text = "", s2.order = 0;
      break;
    default:
      s2.text = JSON.stringify(s2.data);
  }
  return s2;
};
var G = (t2) => {
  if (t2 instanceof Object && t2.constructor === Object && t2.hasOwnProperty("data") && ("string" == typeof t2.text || "string" == typeof t2.data)) return t2;
  const e2 = { data: t2 };
  if ("string" == typeof t2) {
    if (t2.length) {
      const s2 = I(`<th>${t2}</th>`);
      if (s2.childNodes && (1 !== s2.childNodes.length || "#text" !== s2.childNodes[0].nodeName)) {
        e2.data = s2.childNodes, e2.type = "html";
        const t3 = i(s2);
        e2.text = t3;
      }
    }
  } else [null, void 0].includes(t2) ? e2.text = "" : e2.text = JSON.stringify(t2);
  return e2;
};
var K = (t2, e2 = void 0, s2, n2, a2) => {
  var _a, _b;
  const o2 = { data: [], headings: [] };
  if (t2.headings) o2.headings = t2.headings.map((t3) => G(t3));
  else if (e2 == null ? void 0 : e2.tHead) o2.headings = Array.from(e2.tHead.querySelectorAll("th")).map((t3, e3) => {
    var _a2, _b2, _c, _d, _e;
    const o3 = ((t4) => {
      const e4 = T(t4, { valueDiffing: false });
      let s3;
      return s3 = !e4.childNodes || 1 === e4.childNodes.length && "#text" === e4.childNodes[0].nodeName ? { data: t4.innerText, type: "string" } : { data: e4.childNodes, type: "html", text: i(e4) }, s3.attributes = e4.attributes, s3;
    })(t3);
    s2[e3] || (s2[e3] = { type: n2, format: a2, searchable: true, sortable: true });
    const r2 = s2[e3];
    return "false" !== ((_a2 = t3.dataset.sortable) == null ? void 0 : _a2.trim().toLowerCase()) && "false" !== ((_b2 = t3.dataset.sort) == null ? void 0 : _b2.trim().toLowerCase()) || (r2.sortable = false), "false" === ((_c = t3.dataset.searchable) == null ? void 0 : _c.trim().toLowerCase()) && (r2.searchable = false), "true" !== ((_d = t3.dataset.hidden) == null ? void 0 : _d.trim().toLowerCase()) && "true" !== ((_e = t3.getAttribute("hidden")) == null ? void 0 : _e.trim().toLowerCase()) || (r2.hidden = true), ["number", "string", "html", "date", "boolean", "other"].includes(t3.dataset.type) && (r2.type = t3.dataset.type, "date" === r2.type && t3.dataset.format && (r2.format = t3.dataset.format)), o3;
  });
  else if ((_a = t2.data) == null ? void 0 : _a.length) {
    const e3 = t2.data[0], s3 = Array.isArray(e3) ? e3 : e3.cells;
    o2.headings = s3.map((t3) => G(""));
  } else (e2 == null ? void 0 : e2.tBodies.length) && (o2.headings = Array.from(e2.tBodies[0].rows[0].cells).map((t3) => G("")));
  for (let t3 = 0; t3 < o2.headings.length; t3++) s2[t3] || (s2[t3] = { type: n2, format: a2, sortable: true, searchable: true });
  if (t2.data) {
    const e3 = o2.headings.map((t3) => t3.data ? String(t3.data) : t3.text);
    o2.data = t2.data.map((t3) => {
      let i2, n3;
      return Array.isArray(t3) ? (i2 = {}, n3 = t3) : t3.hasOwnProperty("cells") && Object.keys(t3).every((t4) => ["cells", "attributes"].includes(t4)) ? (i2 = t3.attributes, n3 = t3.cells) : (i2 = {}, n3 = [], Object.entries(t3).forEach(([t4, s3]) => {
        const i3 = e3.indexOf(t4);
        i3 > -1 && (n3[i3] = s3);
      })), { attributes: i2, cells: n3.map((t4, e4) => Z(t4, s2[e4])) };
    });
  } else ((_b = e2 == null ? void 0 : e2.tBodies) == null ? void 0 : _b.length) && (o2.data = Array.from(e2.tBodies[0].rows).map((t3) => ({ attributes: r(t3.attributes), cells: Array.from(t3.cells).map((t4, e3) => {
    const i2 = t4.dataset.content ? Z(t4.dataset.content, s2[e3]) : ((t5, e4) => {
      let s3;
      switch (e4.type) {
        case "string":
          s3 = { data: t5.innerText };
          break;
        case "date": {
          const i3 = t5.innerText;
          s3 = { data: i3, order: X(i3, e4.format) };
          break;
        }
        case "number": {
          const e5 = parseFloat(t5.innerText);
          s3 = { data: e5, order: e5, text: t5.innerText };
          break;
        }
        case "boolean": {
          const e5 = !["false", "0", "null", "undefined"].includes(t5.innerText.toLowerCase().trim());
          s3 = { data: e5, text: e5 ? "1" : "0", order: e5 ? 1 : 0 };
          break;
        }
        default:
          s3 = { data: T(t5, { valueDiffing: false }).childNodes || [], text: t5.innerText, order: t5.innerText };
      }
      return s3.attributes = r(t5.attributes), s3;
    })(t4, s2[e3]);
    return t4.dataset.order && (i2.order = isNaN(parseFloat(t4.dataset.order)) ? t4.dataset.order : parseFloat(t4.dataset.order)), i2;
  }) })));
  if (o2.data.length && o2.data[0].cells.length !== o2.headings.length) throw new Error("Data heading length mismatch.");
  return o2;
};
var tt = class {
  constructor(t2) {
    __publicField(this, "cursor");
    __publicField(this, "dt");
    this.dt = t2, this.cursor = false;
  }
  setCursor(t2 = false) {
    if (t2 === this.cursor) return;
    const e2 = this.cursor;
    if (this.cursor = t2, this.dt._renderTable(), false !== t2 && this.dt.options.scrollY) {
      const t3 = l(this.dt.options.classes.cursor), e3 = this.dt.dom.querySelector(`tr${t3}`);
      e3 && e3.scrollIntoView({ block: "nearest" });
    }
    this.dt.emit("datatable.cursormove", this.cursor, e2);
  }
  add(t2) {
    if (!Array.isArray(t2) || t2.length < 1) return;
    const e2 = { cells: t2.map((t3, e3) => {
      const s2 = this.dt.columns.settings[e3];
      return Z(t3, s2);
    }) };
    this.dt.data.data.push(e2), this.dt.hasRows = true, this.dt.update(true);
  }
  remove(t2) {
    if (!Array.isArray(t2)) return this.remove([t2]);
    this.dt.data.data = this.dt.data.data.filter((e2, s2) => !t2.includes(s2)), this.dt.data.data.length || (this.dt.hasRows = false), this.dt.update(true);
  }
  findRowIndex(t2, e2) {
    return this.dt.data.data.findIndex((s2) => {
      const i2 = s2.cells[t2];
      return n(i2).toLowerCase().includes(String(e2).toLowerCase());
    });
  }
  findRow(t2, e2) {
    const s2 = this.findRowIndex(t2, e2);
    if (s2 < 0) return { index: -1, row: null, cols: [] };
    const i2 = this.dt.data.data[s2], n2 = i2.cells.map((t3) => t3.data);
    return { index: s2, row: i2, cols: n2 };
  }
  updateRow(t2, e2) {
    const s2 = { cells: e2.map((t3, e3) => {
      const s3 = this.dt.columns.settings[e3];
      return Z(t3, s3);
    }) };
    this.dt.data.data.splice(t2, 1, s2), this.dt.update(true);
  }
};
var et = class {
  constructor(t2) {
    __publicField(this, "dt");
    __publicField(this, "settings");
    __publicField(this, "_state");
    this.dt = t2, this.init();
  }
  init() {
    [this.settings, this._state] = ((t2 = [], e2, s2) => {
      let i2 = [], n2 = false;
      const a2 = [];
      return t2.forEach((t3) => {
        (Array.isArray(t3.select) ? t3.select : [t3.select]).forEach((o2) => {
          i2[o2] ? t3.type && (i2[o2].type = t3.type) : i2[o2] = { type: t3.type || e2, sortable: true, searchable: true };
          const r2 = i2[o2];
          t3.render && (r2.render = t3.render), t3.format ? r2.format = t3.format : "date" === t3.type && (r2.format = s2), t3.cellClass && (r2.cellClass = t3.cellClass), t3.headerClass && (r2.headerClass = t3.headerClass), t3.locale && (r2.locale = t3.locale), false === t3.sortable ? r2.sortable = false : (t3.numeric && (r2.numeric = t3.numeric), t3.caseFirst && (r2.caseFirst = t3.caseFirst)), false === t3.searchable ? r2.searchable = false : t3.sensitivity && (r2.sensitivity = t3.sensitivity), (r2.searchable || r2.sortable) && void 0 !== t3.ignorePunctuation && (r2.ignorePunctuation = t3.ignorePunctuation), t3.searchMethod && (r2.searchMethod = t3.searchMethod), t3.hidden && (r2.hidden = true), t3.filter && (r2.filter = t3.filter), t3.sortSequence && (r2.sortSequence = t3.sortSequence), t3.sort && (t3.filter ? a2[o2] = t3.sort : n2 = { column: o2, dir: t3.sort }), void 0 !== t3.searchItemSeparator && (r2.searchItemSeparator = t3.searchItemSeparator);
        });
      }), i2 = i2.map((t3) => t3 || { type: e2, format: "date" === e2 ? s2 : void 0, sortable: true, searchable: true }), [i2, { filters: a2, sort: n2, widths: [] }];
    })(this.dt.options.columns, this.dt.options.type, this.dt.options.format);
  }
  get(t2) {
    return t2 < 0 || t2 >= this.size() ? null : { ...this.settings[t2] };
  }
  size() {
    return this.settings.length;
  }
  swap(t2) {
    if (2 === t2.length) {
      const e2 = this.dt.data.headings.map((t3, e3) => e3), s2 = t2[0], i2 = t2[1], n2 = e2[i2];
      return e2[i2] = e2[s2], e2[s2] = n2, this.order(e2);
    }
  }
  order(t2) {
    this.dt.data.headings = t2.map((t3) => this.dt.data.headings[t3]), this.dt.data.data.forEach((e2) => e2.cells = t2.map((t3) => e2.cells[t3])), this.settings = t2.map((t3) => this.settings[t3]), this.dt.update();
  }
  hide(t2) {
    Array.isArray(t2) || (t2 = [t2]), t2.length && (t2.forEach((t3) => {
      this.settings[t3] || (this.settings[t3] = { type: "string" });
      this.settings[t3].hidden = true;
    }), this.dt.update());
  }
  show(t2) {
    Array.isArray(t2) || (t2 = [t2]), t2.length && (t2.forEach((t3) => {
      this.settings[t3] || (this.settings[t3] = { type: "string", sortable: true });
      delete this.settings[t3].hidden;
    }), this.dt.update());
  }
  visible(t2) {
    var _a;
    return void 0 === t2 && (t2 = [...Array(this.dt.data.headings.length).keys()]), Array.isArray(t2) ? t2.map((t3) => {
      var _a2;
      return !((_a2 = this.settings[t3]) == null ? void 0 : _a2.hidden);
    }) : !((_a = this.settings[t2]) == null ? void 0 : _a.hidden);
  }
  add(t2) {
    const e2 = this.dt.data.headings.length;
    if (this.dt.data.headings = this.dt.data.headings.concat([G(t2.heading)]), this.dt.data.data.forEach((e3, s2) => {
      e3.cells = e3.cells.concat([Z(t2.data[s2], t2)]);
    }), this.settings[e2] = { type: t2.type || "string", sortable: true, searchable: true }, t2.type || t2.format || t2.sortable || t2.render || t2.filter) {
      const s2 = this.settings[e2];
      t2.render && (s2.render = t2.render), t2.format && (s2.format = t2.format), t2.cellClass && (s2.cellClass = t2.cellClass), t2.headerClass && (s2.headerClass = t2.headerClass), t2.locale && (s2.locale = t2.locale), false === t2.sortable ? s2.sortable = false : (t2.numeric && (s2.numeric = t2.numeric), t2.caseFirst && (s2.caseFirst = t2.caseFirst)), false === t2.searchable ? s2.searchable = false : t2.sensitivity && (s2.sensitivity = t2.sensitivity), (s2.searchable || s2.sortable) && t2.ignorePunctuation && (s2.ignorePunctuation = t2.ignorePunctuation), t2.hidden && (s2.hidden = true), t2.filter && (s2.filter = t2.filter), t2.sortSequence && (s2.sortSequence = t2.sortSequence);
    }
    this.dt.update(true);
  }
  remove(t2) {
    Array.isArray(t2) || (t2 = [t2]), this.dt.data.headings = this.dt.data.headings.filter((e2, s2) => !t2.includes(s2)), this.dt.data.data.forEach((e2) => e2.cells = e2.cells.filter((e3, s2) => !t2.includes(s2))), this.dt.update(true);
  }
  filter(t2, e2 = false) {
    var _a, _b;
    if (!((_b = (_a = this.settings[t2]) == null ? void 0 : _a.filter) == null ? void 0 : _b.length)) return;
    const s2 = this._state.filters[t2];
    let i2;
    if (s2) {
      let e3 = false;
      i2 = this.settings[t2].filter.find((t3) => !!e3 || (t3 === s2 && (e3 = true), false));
    } else {
      const e3 = this.settings[t2].filter;
      i2 = e3 ? e3[0] : void 0;
    }
    i2 ? this._state.filters[t2] = i2 : s2 && (this._state.filters[t2] = void 0), this.dt._currentPage = 1, this.dt.update(), e2 || this.dt.emit("datatable.filter", t2, i2);
  }
  sort(t2, e2 = void 0, s2 = false) {
    var _a;
    const i2 = this.settings[t2];
    if (s2 || this.dt.emit("datatable.sorting", t2, e2), !e2) {
      const s3 = !(!this._state.sort || this._state.sort.column !== t2) && ((_a = this._state.sort) == null ? void 0 : _a.dir), n2 = (i2 == null ? void 0 : i2.sortSequence) || ["asc", "desc"];
      if (s3) {
        const t3 = n2.indexOf(s3);
        e2 = -1 === t3 ? n2[0] || "asc" : t3 === n2.length - 1 ? n2[0] : n2[t3 + 1];
      } else e2 = n2.length ? n2[0] : "asc";
    }
    const a2 = !!["string", "html"].includes(i2.type) && new Intl.Collator(i2.locale || this.dt.options.locale, { usage: "sort", numeric: i2.numeric || this.dt.options.numeric, caseFirst: i2.caseFirst || this.dt.options.caseFirst, ignorePunctuation: i2.ignorePunctuation || this.dt.options.ignorePunctuation });
    this.dt.data.data.sort((s3, i3) => {
      const o2 = s3.cells[t2], r2 = i3.cells[t2];
      let l2 = o2.order ?? n(o2), d2 = r2.order ?? n(r2);
      if ("desc" === e2) {
        const t3 = l2;
        l2 = d2, d2 = t3;
      }
      return a2 && "number" != typeof l2 && "number" != typeof d2 ? a2.compare(String(l2), String(d2)) : l2 < d2 ? -1 : l2 > d2 ? 1 : 0;
    }), this._state.sort = { column: t2, dir: e2 }, this.dt._searchQueries.length ? (this.dt.multiSearch(this.dt._searchQueries), this.dt.emit("datatable.sort", t2, e2)) : s2 || (this.dt._currentPage = 1, this.dt.update(), this.dt.emit("datatable.sort", t2, e2));
  }
  _measureWidths() {
    var _a, _b, _c, _d;
    const t2 = this.dt.data.headings.filter((t3, e2) => {
      var _a2;
      return !((_a2 = this.settings[e2]) == null ? void 0 : _a2.hidden);
    });
    if ((this.dt.options.scrollY.length || this.dt.options.fixedColumns) && (t2 == null ? void 0 : t2.length)) {
      this._state.widths = [];
      const t3 = { noPaging: true };
      if (this.dt.options.header || this.dt.options.footer) {
        this.dt.options.scrollY.length && (t3.unhideHeader = true), this.dt.headerDOM && this.dt.headerDOM.parentElement.removeChild(this.dt.headerDOM), t3.noColumnWidths = true, this.dt._renderTable(t3);
        const e2 = Array.from(((_b = (_a = this.dt.dom.querySelector("thead, tfoot")) == null ? void 0 : _a.firstElementChild) == null ? void 0 : _b.querySelectorAll("th")) || []);
        let s2 = 0;
        const i2 = this.dt.data.headings.map((t4, i3) => {
          var _a2;
          if ((_a2 = this.settings[i3]) == null ? void 0 : _a2.hidden) return 0;
          const n3 = e2[s2].offsetWidth;
          return s2 += 1, n3;
        }), n2 = i2.reduce((t4, e3) => t4 + e3, 0);
        this._state.widths = i2.map((t4) => t4 / n2 * 100);
      } else {
        t3.renderHeader = true, this.dt._renderTable(t3);
        const e2 = Array.from(((_d = (_c = this.dt.dom.querySelector("thead, tfoot")) == null ? void 0 : _c.firstElementChild) == null ? void 0 : _d.querySelectorAll("th")) || []);
        let s2 = 0;
        const i2 = this.dt.data.headings.map((t4, i3) => {
          var _a2;
          if ((_a2 = this.settings[i3]) == null ? void 0 : _a2.hidden) return 0;
          const n3 = e2[s2].offsetWidth;
          return s2 += 1, n3;
        }), n2 = i2.reduce((t4, e3) => t4 + e3, 0);
        this._state.widths = i2.map((t4) => t4 / n2 * 100);
      }
      this.dt._renderTable();
    }
  }
};
var st = { sortable: true, locale: "en", numeric: true, caseFirst: "false", searchable: true, sensitivity: "base", ignorePunctuation: true, destroyable: true, searchItemSeparator: "", searchQuerySeparator: " ", searchAnd: false, searchMethod: false, data: {}, type: "html", format: "YYYY-MM-DD", columns: [], paging: true, perPage: 10, perPageSelect: [5, 10, 15, 20, 25], nextPrev: true, firstLast: false, prevText: "‹", nextText: "›", firstText: "«", lastText: "»", ellipsisText: "…", truncatePager: true, pagerDelta: 2, scrollY: "", fixedColumns: true, fixedHeight: false, footer: false, header: true, hiddenHeader: false, caption: void 0, rowNavigation: false, tabIndex: false, pagerRender: false, rowRender: false, tableRender: false, diffDomOptions: { valueDiffing: false }, labels: { placeholder: "Search...", searchTitle: "Search within table", perPage: "entries per page", pageTitle: "Page {page}", noRows: "No entries found", noResults: "No results match your search query", info: "Showing {start} to {end} of {rows} entries" }, template: (t2, e2) => `<div class='${t2.classes.top}'>
    ${t2.paging && t2.perPageSelect ? `<div class='${t2.classes.dropdown}'>
            <label>
                <select class='${t2.classes.selector}' name="per-page"></select> ${t2.labels.perPage}
            </label>
        </div>` : ""}
    ${t2.searchable ? `<div class='${t2.classes.search}'>
            <input class='${t2.classes.input}' placeholder='${t2.labels.placeholder}' type='search' name="search" title='${t2.labels.searchTitle}'${e2.id ? ` aria-controls="${e2.id}"` : ""}>
        </div>` : ""}
</div>
<div class='${t2.classes.container}'${t2.scrollY.length ? ` style='height: ${t2.scrollY}; overflow-Y: auto;'` : ""}></div>
<div class='${t2.classes.bottom}'>
    ${t2.paging ? `<div class='${t2.classes.info}'></div>` : ""}
    <nav class='${t2.classes.pagination}'></nav>
</div>`, classes: { active: "datatable-active", ascending: "datatable-ascending", bottom: "datatable-bottom", container: "datatable-container", cursor: "datatable-cursor", descending: "datatable-descending", disabled: "datatable-disabled", dropdown: "datatable-dropdown", ellipsis: "datatable-ellipsis", filter: "datatable-filter", filterActive: "datatable-filter-active", empty: "datatable-empty", headercontainer: "datatable-headercontainer", hidden: "datatable-hidden", info: "datatable-info", input: "datatable-input", loading: "datatable-loading", pagination: "datatable-pagination", paginationList: "datatable-pagination-list", paginationListItem: "datatable-pagination-list-item", paginationListItemLink: "datatable-pagination-list-item-link", search: "datatable-search", selector: "datatable-selector", sorter: "datatable-sorter", table: "datatable-table", top: "datatable-top", wrapper: "datatable-wrapper" } };
var it = (t2, e2, s2, i2 = {}) => ({ nodeName: "LI", attributes: { class: i2.active && !i2.hidden ? `${s2.classes.paginationListItem} ${s2.classes.active}` : i2.hidden ? `${s2.classes.paginationListItem} ${s2.classes.hidden} ${s2.classes.disabled}` : s2.classes.paginationListItem }, childNodes: [{ nodeName: "BUTTON", attributes: { "data-page": String(t2), class: s2.classes.paginationListItemLink, "aria-label": s2.labels.pageTitle.replace("{page}", String(t2)) }, childNodes: [{ nodeName: "#text", data: e2 }] }] });
var nt = (t2, e2, s2, i2, n2) => {
  let a2 = [];
  if (n2.firstLast && a2.push(it(1, n2.firstText, n2)), n2.nextPrev) {
    const e3 = t2 ? 1 : s2 - 1;
    a2.push(it(e3, n2.prevText, n2, { hidden: t2 }));
  }
  let o2 = [...Array(i2).keys()].map((t3) => it(t3 + 1, String(t3 + 1), n2, { active: t3 === s2 - 1 }));
  if (n2.truncatePager && (o2 = ((t3, e3, s3, i3) => {
    const n3 = i3.pagerDelta, a3 = i3.classes, o3 = i3.ellipsisText, r2 = 2 * n3;
    let l2 = e3 - n3, d2 = e3 + n3;
    e3 < 4 - n3 + r2 ? d2 = 3 + r2 : e3 > s3 - (3 - n3 + r2) && (l2 = s3 - (2 + r2));
    const c2 = [];
    for (let e4 = 1; e4 <= s3; e4++) if (1 == e4 || e4 == s3 || e4 >= l2 && e4 <= d2) {
      const s4 = t3[e4 - 1];
      c2.push(s4);
    }
    let h2;
    const u2 = [];
    return c2.forEach((e4) => {
      const s4 = parseInt(e4.childNodes[0].attributes["data-page"], 10);
      if (h2) {
        const e5 = parseInt(h2.childNodes[0].attributes["data-page"], 10);
        if (s4 - e5 == 2) u2.push(t3[e5]);
        else if (s4 - e5 != 1) {
          const t4 = { nodeName: "LI", attributes: { class: `${a3.paginationListItem} ${a3.ellipsis} ${a3.disabled}` }, childNodes: [{ nodeName: "BUTTON", attributes: { class: a3.paginationListItemLink }, childNodes: [{ nodeName: "#text", data: o3 }] }] };
          u2.push(t4);
        }
      }
      u2.push(e4), h2 = e4;
    }), u2;
  })(o2, s2, i2, n2)), a2 = a2.concat(o2), n2.nextPrev) {
    const t3 = e2 ? i2 : s2 + 1;
    a2.push(it(t3, n2.nextText, n2, { hidden: e2 }));
  }
  n2.firstLast && a2.push(it(i2, n2.lastText, n2));
  return { nodeName: "UL", attributes: { class: n2.classes.paginationList }, childNodes: o2.length > 1 ? a2 : [] };
};
var at = class {
  constructor(t2, e2 = {}) {
    __publicField(this, "columns");
    __publicField(this, "containerDOM");
    __publicField(this, "_currentPage");
    __publicField(this, "data");
    __publicField(this, "_dd");
    __publicField(this, "dom");
    __publicField(this, "_events");
    __publicField(this, "hasHeadings");
    __publicField(this, "hasRows");
    __publicField(this, "headerDOM");
    __publicField(this, "_initialHTML");
    __publicField(this, "initialized");
    __publicField(this, "_label");
    __publicField(this, "lastPage");
    __publicField(this, "_listeners");
    __publicField(this, "onFirstPage");
    __publicField(this, "onLastPage");
    __publicField(this, "options");
    __publicField(this, "_pagerDOMs");
    __publicField(this, "_virtualPagerDOM");
    __publicField(this, "pages");
    __publicField(this, "_rect");
    __publicField(this, "rows");
    __publicField(this, "_searchData");
    __publicField(this, "_searchQueries");
    __publicField(this, "_tableAttributes");
    __publicField(this, "_tableFooters");
    __publicField(this, "_tableCaptions");
    __publicField(this, "totalPages");
    __publicField(this, "_virtualDOM");
    __publicField(this, "_virtualHeaderDOM");
    __publicField(this, "wrapperDOM");
    __publicField(this, "_onResize", h(() => {
      this._rect = this.containerDOM.getBoundingClientRect(), this._rect.width && this.update(true);
    }, 250));
    const s2 = "string" == typeof t2 ? document.querySelector(t2) : t2;
    s2 instanceof HTMLTableElement ? this.dom = s2 : (this.dom = document.createElement("table"), s2.appendChild(this.dom));
    const i2 = { ...st.diffDomOptions, ...e2.diffDomOptions }, n2 = { ...st.labels, ...e2.labels }, a2 = { ...st.classes, ...e2.classes };
    this.options = { ...st, ...e2, diffDomOptions: i2, labels: n2, classes: a2 }, this._initialHTML = this.options.destroyable ? s2.outerHTML : "", this.options.tabIndex ? this.dom.tabIndex = this.options.tabIndex : this.options.rowNavigation && -1 === this.dom.tabIndex && (this.dom.tabIndex = 0), this._listeners = { onResize: () => this._onResize() }, this._dd = new q(this.options.diffDomOptions || {}), this.initialized = false, this._events = {}, this._currentPage = 0, this.onFirstPage = true, this.hasHeadings = false, this.hasRows = false, this._searchQueries = [], this.init();
  }
  init() {
    var _a, _b;
    if (this.initialized || d(this.dom, this.options.classes.table)) return false;
    this._virtualDOM = T(this.dom, this.options.diffDomOptions || {}), this._tableAttributes = { ...this._virtualDOM.attributes }, this._tableFooters = ((_a = this._virtualDOM.childNodes) == null ? void 0 : _a.filter((t2) => "TFOOT" === t2.nodeName)) ?? [], this._tableCaptions = ((_b = this._virtualDOM.childNodes) == null ? void 0 : _b.filter((t2) => "CAPTION" === t2.nodeName)) ?? [], void 0 !== this.options.caption && this._tableCaptions.push({ nodeName: "CAPTION", childNodes: [{ nodeName: "#text", data: this.options.caption }] }), this.rows = new tt(this), this.columns = new et(this), this.data = K(this.options.data, this.dom, this.columns.settings, this.options.type, this.options.format), this._render(), setTimeout(() => {
      this.emit("datatable.init"), this.initialized = true;
    }, 10);
  }
  _render() {
    this.wrapperDOM = s("div", { class: `${this.options.classes.wrapper} ${this.options.classes.loading}` }), this.wrapperDOM.innerHTML = this.options.template(this.options, this.dom);
    const t2 = l(this.options.classes.selector), e2 = this.wrapperDOM.querySelector(`select${t2}`);
    e2 && this.options.paging && this.options.perPageSelect ? this.options.perPageSelect.forEach((t3) => {
      const [s2, i3] = Array.isArray(t3) ? [t3[0], t3[1]] : [String(t3), t3], n3 = i3 === this.options.perPage, a3 = new Option(s2, String(i3), n3, n3);
      e2.appendChild(a3);
    }) : e2 && e2.parentElement.removeChild(e2);
    const i2 = l(this.options.classes.container);
    this.containerDOM = this.wrapperDOM.querySelector(i2), this._pagerDOMs = [];
    const n2 = l(this.options.classes.pagination);
    Array.from(this.wrapperDOM.querySelectorAll(n2)).forEach((t3) => {
      t3 instanceof HTMLElement && (t3.innerHTML = `<ul class="${this.options.classes.paginationList}"></ul>`, this._pagerDOMs.push(t3.firstElementChild));
    }), this._virtualPagerDOM = { nodeName: "UL", attributes: { class: this.options.classes.paginationList } };
    const a2 = l(this.options.classes.info);
    this._label = this.wrapperDOM.querySelector(a2), this.dom.parentElement.replaceChild(this.wrapperDOM, this.dom), this.containerDOM.appendChild(this.dom), this._rect = this.dom.getBoundingClientRect(), this._fixHeight(), this.options.header || this.wrapperDOM.classList.add("no-header"), this.options.footer || this.wrapperDOM.classList.add("no-footer"), this.options.sortable && this.wrapperDOM.classList.add("sortable"), this.options.searchable && this.wrapperDOM.classList.add("searchable"), this.options.fixedHeight && this.wrapperDOM.classList.add("fixed-height"), this.options.fixedColumns && this.wrapperDOM.classList.add("fixed-columns"), this._bindEvents(), this.columns._state.sort && this.columns.sort(this.columns._state.sort.column, this.columns._state.sort.dir, true), this.update(true);
  }
  _renderTable(t2 = {}) {
    let e2;
    e2 = (this.options.paging || this._searchQueries.length || this.columns._state.filters.length) && this._currentPage && this.pages.length && !t2.noPaging ? this.pages[this._currentPage - 1] : this.data.data.map((t3, e3) => ({ row: t3, index: e3 }));
    let s2 = B(this._tableAttributes, this.data.headings, e2, this.columns.settings, this.columns._state, this.rows.cursor, this.options, t2, this._tableFooters, this._tableCaptions);
    if (this.options.tableRender) {
      const t3 = this.options.tableRender(this.data, s2, "main");
      t3 && (s2 = t3);
    }
    const i2 = this._dd.diff(this._virtualDOM, s2);
    this._dd.apply(this.dom, i2), this._virtualDOM = s2;
  }
  _renderPage(t2 = false) {
    this.hasRows && this.totalPages ? (this._currentPage > this.totalPages && (this._currentPage = 1), this._renderTable(), this.onFirstPage = 1 === this._currentPage, this.onLastPage = this._currentPage === this.lastPage) : this.setMessage(this.options.labels.noRows);
    let e2, s2 = 0, i2 = 0, n2 = 0;
    if (this.totalPages && (s2 = this._currentPage - 1, i2 = s2 * this.options.perPage, n2 = i2 + this.pages[s2].length, i2 += 1, e2 = this._searchQueries.length ? this._searchData.length : this.data.data.length), this._label && this.options.labels.info.length) {
      const t3 = this.options.labels.info.replace("{start}", String(i2)).replace("{end}", String(n2)).replace("{page}", String(this._currentPage)).replace("{pages}", String(this.totalPages)).replace("{rows}", String(e2));
      this._label.innerHTML = e2 ? t3 : "";
    }
    if (1 == this._currentPage && this._fixHeight(), this.options.rowNavigation && this._currentPage && (!this.rows.cursor || !this.pages[this._currentPage - 1].find((t3) => t3.index === this.rows.cursor))) {
      const e3 = this.pages[this._currentPage - 1];
      e3.length && (t2 ? this.rows.setCursor(e3[e3.length - 1].index) : this.rows.setCursor(e3[0].index));
    }
  }
  _renderPagers() {
    if (!this.options.paging) return;
    let t2 = nt(this.onFirstPage, this.onLastPage, this._currentPage, this.totalPages, this.options);
    if (this.options.pagerRender) {
      const e3 = this.options.pagerRender([this.onFirstPage, this.onLastPage, this._currentPage, this.totalPages], t2);
      e3 && (t2 = e3);
    }
    const e2 = this._dd.diff(this._virtualPagerDOM, t2);
    this._pagerDOMs.forEach((t3) => {
      this._dd.apply(t3, e2);
    }), this._virtualPagerDOM = t2;
  }
  _renderSeparateHeader() {
    const t2 = this.dom.parentElement;
    this.headerDOM || (this.headerDOM = document.createElement("div"), this._virtualHeaderDOM = { nodeName: "DIV" }), t2.parentElement.insertBefore(this.headerDOM, t2);
    let e2 = { nodeName: "TABLE", attributes: this._tableAttributes, childNodes: [{ nodeName: "THEAD", childNodes: [F(this.data.headings, this.columns.settings, this.columns._state, this.options, { unhideHeader: true })] }] };
    if (e2.attributes.class = c(e2.attributes.class, this.options.classes.table), this.options.tableRender) {
      const t3 = this.options.tableRender(this.data, e2, "header");
      t3 && (e2 = t3);
    }
    const s2 = { nodeName: "DIV", attributes: { class: this.options.classes.headercontainer }, childNodes: [e2] }, i2 = this._dd.diff(this._virtualHeaderDOM, s2);
    this._dd.apply(this.headerDOM, i2), this._virtualHeaderDOM = s2;
    const n2 = this.headerDOM.firstElementChild.clientWidth - this.dom.clientWidth;
    if (n2) {
      const t3 = structuredClone(this._virtualHeaderDOM);
      t3.attributes.style = `padding-right: ${n2}px;`;
      const e3 = this._dd.diff(this._virtualHeaderDOM, t3);
      this._dd.apply(this.headerDOM, e3), this._virtualHeaderDOM = t3;
    }
    t2.scrollHeight > t2.clientHeight && (t2.style.overflowY = "scroll");
  }
  _bindEvents() {
    if (this.options.perPageSelect) {
      const t2 = l(this.options.classes.selector), e2 = this.wrapperDOM.querySelector(t2);
      e2 && e2 instanceof HTMLSelectElement && e2.addEventListener("change", () => {
        this.emit("datatable.perpage:before", this.options.perPage), this.options.perPage = parseInt(e2.value, 10), this.update(), this._fixHeight(), this.emit("datatable.perpage", this.options.perPage);
      }, false);
    }
    this.options.searchable && this.wrapperDOM.addEventListener("input", (t2) => {
      const e2 = l(this.options.classes.input), s2 = t2.target;
      if (!(s2 instanceof HTMLInputElement && s2.matches(e2))) return;
      t2.preventDefault();
      const i2 = [];
      if (Array.from(this.wrapperDOM.querySelectorAll(e2)).filter((t3) => t3.value.length).forEach((t3) => {
        const e3 = t3.dataset.and || this.options.searchAnd, s3 = t3.dataset.querySeparator || this.options.searchQuerySeparator ? t3.value.split(this.options.searchQuerySeparator) : [t3.value];
        e3 ? s3.forEach((e4) => {
          t3.dataset.columns ? i2.push({ terms: [e4], columns: JSON.parse(t3.dataset.columns) }) : i2.push({ terms: [e4], columns: void 0 });
        }) : t3.dataset.columns ? i2.push({ terms: s3, columns: JSON.parse(t3.dataset.columns) }) : i2.push({ terms: s3, columns: void 0 });
      }), 1 === i2.length && 1 === i2[0].terms.length) {
        const t3 = i2[0];
        this.search(t3.terms[0], t3.columns);
      } else this.multiSearch(i2);
    }), this.wrapperDOM.addEventListener("click", (t2) => {
      const e2 = t2.target.closest("a, button");
      if (e2) {
        if (e2.hasAttribute("data-page")) this.page(parseInt(e2.getAttribute("data-page"), 10)), t2.preventDefault();
        else if (d(e2, this.options.classes.sorter)) {
          const s2 = Array.from(e2.parentElement.parentElement.children).indexOf(e2.parentElement), i2 = o(s2, this.columns.settings);
          this.columns.sort(i2), t2.preventDefault();
        } else if (d(e2, this.options.classes.filter)) {
          const s2 = Array.from(e2.parentElement.parentElement.children).indexOf(e2.parentElement), i2 = o(s2, this.columns.settings);
          this.columns.filter(i2), t2.preventDefault();
        }
      }
    }, false), this.options.rowNavigation ? (this.dom.addEventListener("keydown", (t2) => {
      if ("ArrowUp" === t2.key) {
        let e2;
        t2.preventDefault(), t2.stopPropagation(), this.pages[this._currentPage - 1].find((t3) => t3.index === this.rows.cursor || (e2 = t3, false)), e2 ? this.rows.setCursor(e2.index) : this.onFirstPage || this.page(this._currentPage - 1, true);
      } else if ("ArrowDown" === t2.key) {
        let e2;
        t2.preventDefault(), t2.stopPropagation();
        const s2 = this.pages[this._currentPage - 1].find((t3) => !!e2 || (t3.index === this.rows.cursor && (e2 = true), false));
        s2 ? this.rows.setCursor(s2.index) : this.onLastPage || this.page(this._currentPage + 1);
      } else ["Enter", " "].includes(t2.key) && this.emit("datatable.selectrow", this.rows.cursor, t2);
    }), this.dom.addEventListener("mousedown", (t2) => {
      const e2 = t2.target;
      if (e2 instanceof Element && this.dom.matches(":focus")) {
        const s2 = Array.from(this.dom.querySelectorAll("tbody > tr")).find((t3) => t3.contains(e2));
        s2 && s2 instanceof HTMLElement && this.emit("datatable.selectrow", parseInt(s2.dataset.index, 10), t2);
      }
    })) : this.dom.addEventListener("mousedown", (t2) => {
      const e2 = t2.target;
      if (!(e2 instanceof Element)) return;
      const s2 = Array.from(this.dom.querySelectorAll("tbody > tr")).find((t3) => t3.contains(e2));
      s2 && s2 instanceof HTMLElement && this.emit("datatable.selectrow", parseInt(s2.dataset.index, 10), t2);
    }), window.addEventListener("resize", this._listeners.onResize);
  }
  destroy() {
    var _a;
    if (this.options.destroyable) {
      if (this.wrapperDOM) {
        const t2 = this.wrapperDOM.parentElement;
        if (t2) {
          const e2 = s("div");
          e2.innerHTML = this._initialHTML;
          const i2 = e2.firstElementChild;
          t2.replaceChild(i2, this.wrapperDOM), this.dom = i2;
        } else (_a = this.options.classes.table) == null ? void 0 : _a.split(" ").forEach((t3) => this.wrapperDOM.classList.remove(t3));
      }
      window.removeEventListener("resize", this._listeners.onResize), this.initialized = false;
    }
  }
  update(t2 = false) {
    var _a;
    this.emit("datatable.update:before"), t2 && (this.columns._measureWidths(), this.hasRows = Boolean(this.data.data.length), this.hasHeadings = Boolean(this.data.headings.length)), (_a = this.options.classes.empty) == null ? void 0 : _a.split(" ").forEach((t3) => this.wrapperDOM.classList.remove(t3)), this._paginate(), this._renderPage(), this._renderPagers(), this.options.scrollY.length && this._renderSeparateHeader(), this.emit("datatable.update");
  }
  _paginate() {
    let t2 = this.data.data.map((t3, e2) => ({ row: t3, index: e2 }));
    return this._searchQueries.length && (t2 = [], this._searchData.forEach((e2) => t2.push({ index: e2, row: this.data.data[e2] }))), this.columns._state.filters.length && this.columns._state.filters.forEach((e2, s2) => {
      e2 && (t2 = t2.filter((t3) => {
        const i2 = t3.row.cells[s2];
        return "function" == typeof e2 ? e2(i2.data) : n(i2) === e2;
      }));
    }), this.options.paging && this.options.perPage > 0 ? this.pages = t2.map((e2, s2) => s2 % this.options.perPage == 0 ? t2.slice(s2, s2 + this.options.perPage) : null).filter((t3) => t3) : this.pages = [t2], this.totalPages = this.lastPage = this.pages.length, this._currentPage || (this._currentPage = 1), this.totalPages;
  }
  _fixHeight() {
    this.options.fixedHeight && (this.containerDOM.style.height = null, this._rect = this.containerDOM.getBoundingClientRect(), this.containerDOM.style.height = `${this._rect.height}px`);
  }
  search(t2, e2 = void 0, s2 = "search") {
    if (this.emit("datatable.search:before", t2, this._searchData), !t2.length) return this._currentPage = 1, this._searchQueries = [], this._searchData = [], this.update(), this.emit("datatable.search", "", []), this.wrapperDOM.classList.remove("search-results"), false;
    this.multiSearch([{ terms: [t2], columns: e2 || void 0 }], s2), this.emit("datatable.search", t2, this._searchData);
  }
  multiSearch(t2, e2 = "search") {
    if (!this.hasRows) return false;
    this._currentPage = 1, this._searchData = [];
    let s2 = t2.map((t3) => ({ columns: t3.columns, terms: t3.terms.map((t4) => t4.trim()).filter((t4) => t4), source: e2 })).filter((t3) => t3.terms.length);
    if (this.emit("datatable.multisearch:before", s2, this._searchData), e2.length && (s2 = s2.concat(this._searchQueries.filter((t3) => t3.source !== e2))), this._searchQueries = s2, !s2.length) return this.update(), this.emit("datatable.multisearch", s2, this._searchData), this.wrapperDOM.classList.remove("search-results"), false;
    const i2 = s2.map((t3) => this.columns.settings.map((e3, s3) => {
      if (e3.hidden || !e3.searchable || t3.columns && !t3.columns.includes(s3)) return false;
      let i3 = t3.terms;
      const n2 = e3.sensitivity || this.options.sensitivity;
      ["base", "accent"].includes(n2) && (i3 = i3.map((t4) => t4.toLowerCase())), ["base", "case"].includes(n2) && (i3 = i3.map((t4) => t4.normalize("NFD").replace(new RegExp("\\p{Diacritic}", "gu"), "")));
      return (e3.ignorePunctuation ?? this.options.ignorePunctuation) && (i3 = i3.map((t4) => t4.replace(/[.,/#!$%^&*;:{}=-_`~()]/g, ""))), i3;
    }));
    this.data.data.forEach((t3, e3) => {
      const a2 = t3.cells.map((t4, e4) => {
        const s3 = this.columns.settings[e4];
        if (s3.searchMethod || this.options.searchMethod) return t4;
        let i3 = n(t4).trim();
        if (i3.length) {
          const t5 = s3.sensitivity || this.options.sensitivity;
          ["base", "accent"].includes(t5) && (i3 = i3.toLowerCase()), ["base", "case"].includes(t5) && (i3 = i3.normalize("NFD").replace(new RegExp("\\p{Diacritic}", "gu"), ""));
          (s3.ignorePunctuation ?? this.options.ignorePunctuation) && (i3 = i3.replace(/[.,/#!$%^&*;:{}=-_`~()]/g, ""));
        }
        const a3 = s3.searchItemSeparator || this.options.searchItemSeparator;
        return a3 ? i3.split(a3) : [i3];
      });
      i2.every((e4, i3) => e4.find((e5, n2) => {
        if (!e5) return false;
        const o2 = this.columns.settings[n2].searchMethod || this.options.searchMethod;
        return o2 ? o2(e5, a2[n2], t3, n2, s2[i3].source) : e5.find((t4) => a2[n2].find((e6) => e6.includes(t4)));
      })) && this._searchData.push(e3);
    }), this.wrapperDOM.classList.add("search-results"), this._searchData.length ? this.update() : (this.wrapperDOM.classList.remove("search-results"), this.setMessage(this.options.labels.noResults)), this.emit("datatable.multisearch", s2, this._searchData);
  }
  page(t2, e2 = false) {
    return this.emit("datatable.page:before", t2), t2 !== this._currentPage && (isNaN(t2) || (this._currentPage = t2), !(t2 > this.pages.length || t2 < 0) && (this._renderPage(e2), this._renderPagers(), void this.emit("datatable.page", t2)));
  }
  insert(e2) {
    let s2 = [];
    if (Array.isArray(e2)) {
      const t2 = this.data.headings.map((t3) => t3.data ? String(t3.data) : t3.text);
      e2.forEach((e3, i2) => {
        const n2 = [];
        Object.entries(e3).forEach(([e4, s3]) => {
          const a2 = t2.indexOf(e4);
          a2 > -1 ? n2[a2] = Z(s3, this.columns.settings[a2]) : this.hasHeadings || this.hasRows || 0 !== i2 || (n2[t2.length] = Z(s3, this.columns.settings[t2.length]), t2.push(e4), this.data.headings.push(G(e4)));
        }), s2.push({ cells: n2 });
      });
    } else t(e2) && (!e2.headings || this.hasHeadings || this.hasRows ? e2.data && Array.isArray(e2.data) && (s2 = e2.data.map((t2) => {
      let e3, s3;
      return Array.isArray(t2) ? (e3 = {}, s3 = t2) : (e3 = t2.attributes, s3 = t2.cells), { attributes: e3, cells: s3.map((t3, e4) => Z(t3, this.columns.settings[e4])) };
    })) : this.data = K(e2, void 0, this.columns.settings, this.options.type, this.options.format));
    s2.length && s2.forEach((t2) => this.data.data.push(t2)), this.hasHeadings = Boolean(this.data.headings.length), this.columns._state.sort && this.columns.sort(this.columns._state.sort.column, this.columns._state.sort.dir, true), this.update(true);
  }
  refresh() {
    if (this.emit("datatable.refresh:before"), this.options.searchable) {
      const t2 = l(this.options.classes.input);
      Array.from(this.wrapperDOM.querySelectorAll(t2)).forEach((t3) => t3.value = ""), this._searchQueries = [];
    }
    this._currentPage = 1, this.onFirstPage = true, this.update(true), this.emit("datatable.refresh");
  }
  print() {
    const t2 = s("table");
    let e2 = B(this._tableAttributes, this.data.headings, this.data.data.map((t3, e3) => ({ row: t3, index: e3 })), this.columns.settings, this.columns._state, false, this.options, { noColumnWidths: true, unhideHeader: true }, this._tableFooters, this._tableCaptions);
    if (this.options.tableRender) {
      const t3 = this.options.tableRender(this.data, e2, "print");
      t3 && (e2 = t3);
    }
    const i2 = this._dd.diff({ nodeName: "TABLE" }, e2);
    this._dd.apply(t2, i2);
    const n2 = window.open();
    n2.document.body.appendChild(t2), n2.print();
  }
  setMessage(t2) {
    var _a;
    const e2 = this.data.headings.filter((t3, e3) => {
      var _a2;
      return !((_a2 = this.columns.settings[e3]) == null ? void 0 : _a2.hidden);
    }).length || 1;
    (_a = this.options.classes.empty) == null ? void 0 : _a.split(" ").forEach((t3) => this.wrapperDOM.classList.add(t3)), this._label && (this._label.innerHTML = ""), this.totalPages = 0, this._renderPagers();
    let s2 = { nodeName: "TABLE", attributes: this._tableAttributes, childNodes: [{ nodeName: "THEAD", childNodes: [F(this.data.headings, this.columns.settings, this.columns._state, this.options, {})] }, { nodeName: "TBODY", childNodes: [{ nodeName: "TR", childNodes: [{ nodeName: "TD", attributes: { class: this.options.classes.empty, colspan: String(e2) }, childNodes: [{ nodeName: "#text", data: t2 }] }] }] }] };
    if (this._tableFooters.forEach((t3) => s2.childNodes.push(t3)), this._tableCaptions.forEach((t3) => s2.childNodes.push(t3)), s2.attributes.class = c(s2.attributes.class, this.options.classes.table), this.options.tableRender) {
      const t3 = this.options.tableRender(this.data, s2, "message");
      t3 && (s2 = t3);
    }
    const i2 = this._dd.diff(this._virtualDOM, s2);
    this._dd.apply(this.dom, i2), this._virtualDOM = s2;
  }
  on(t2, e2) {
    this._events[t2] = this._events[t2] || [], this._events[t2].push(e2);
  }
  off(t2, e2) {
    t2 in this._events != false && this._events[t2].splice(this._events[t2].indexOf(e2), 1);
  }
  emit(t2, ...e2) {
    if (t2 in this._events != false) for (let s2 = 0; s2 < this._events[t2].length; s2++) this._events[t2][s2](...e2);
  }
};
var ot = function(e2) {
  let s2;
  if (!t(e2)) return false;
  const i2 = { lineDelimiter: "\n", columnDelimiter: ",", removeDoubleQuotes: false, ...e2 };
  if (i2.data.length) {
    s2 = { data: [] };
    const t2 = i2.data.split(i2.lineDelimiter);
    if (t2.length && (i2.headings && (s2.headings = t2[0].split(i2.columnDelimiter), i2.removeDoubleQuotes && (s2.headings = s2.headings.map((t3) => t3.trim().replace(/(^"|"$)/g, ""))), t2.shift()), t2.forEach((t3, e3) => {
      s2.data[e3] = [];
      const n2 = t3.split(i2.columnDelimiter);
      n2.length && n2.forEach((t4) => {
        i2.removeDoubleQuotes && (t4 = t4.trim().replace(/(^"|"$)/g, "")), s2.data[e3].push(t4);
      });
    })), s2) return s2;
  }
  return false;
};
var rt = function(s2) {
  let i2;
  if (!t(s2)) return false;
  const n2 = { data: "", ...s2 };
  if (n2.data.length || t(n2.data)) {
    const t2 = !!e(n2.data) && JSON.parse(n2.data);
    if (t2 ? (i2 = { headings: [], data: [] }, t2.forEach((t3, e2) => {
      i2.data[e2] = [], Object.entries(t3).forEach(([t4, s3]) => {
        i2.headings.includes(t4) || i2.headings.push(t4), i2.data[e2].push(s3);
      });
    })) : console.warn("That's not valid JSON!"), i2) return i2;
  }
  return false;
};
var lt = function(e2, s2 = {}) {
  if (!e2.hasHeadings && !e2.hasRows) return false;
  if (!t(s2)) return false;
  const i2 = { download: true, skipColumn: [], lineDelimiter: "\n", columnDelimiter: ",", ...s2 }, a2 = (t2) => {
    var _a;
    return !i2.skipColumn.includes(t2) && !((_a = e2.columns.settings[t2]) == null ? void 0 : _a.hidden);
  }, o2 = e2.data.headings.filter((t2, e3) => a2(e3)).map((t2) => t2.text ?? t2.data);
  let r2;
  if (i2.selection) if (Array.isArray(i2.selection)) {
    r2 = [];
    for (let t2 = 0; t2 < i2.selection.length; t2++) r2 = r2.concat(e2.pages[i2.selection[t2] - 1].map((t3) => t3.row));
  } else r2 = e2.pages[i2.selection - 1].map((t2) => t2.row);
  else r2 = e2.data.data;
  let l2 = [];
  if (l2[0] = o2, l2 = l2.concat(r2.map((t2) => t2.cells.filter((t3, e3) => a2(e3)).map((t3) => n(t3)))), l2.length) {
    let t2 = "";
    if (l2.forEach((e3) => {
      e3.forEach((e4) => {
        "string" == typeof e4 && (e4 = (e4 = (e4 = (e4 = (e4 = e4.trim()).replace(/\s{2,}/g, " ")).replace(/\n/g, "  ")).replace(/"/g, '""')).replace(/#/g, "%23")).includes(",") && (e4 = `"${e4}"`), t2 += e4 + i2.columnDelimiter;
      }), t2 = t2.trim().substring(0, t2.length - 1), t2 += i2.lineDelimiter;
    }), t2 = t2.trim().substring(0, t2.length - 1), i2.download) {
      const e3 = document.createElement("a");
      e3.href = encodeURI(`data:text/csv;charset=utf-8,${t2}`), e3.download = `${i2.filename || "datatable_export"}.csv`, document.body.appendChild(e3), e3.click(), document.body.removeChild(e3);
    }
    return t2;
  }
  return false;
};
var dt = function(e2, s2 = {}) {
  if (!e2.hasHeadings && !e2.hasRows) return false;
  if (!t(s2)) return false;
  const i2 = { download: true, skipColumn: [], replacer: null, space: 4, ...s2 }, a2 = (t2) => {
    var _a;
    return !i2.skipColumn.includes(t2) && !((_a = e2.columns.settings[t2]) == null ? void 0 : _a.hidden);
  };
  let o2;
  if (i2.selection) if (Array.isArray(i2.selection)) {
    o2 = [];
    for (let t2 = 0; t2 < i2.selection.length; t2++) o2 = o2.concat(e2.pages[i2.selection[t2] - 1].map((t3) => t3.row));
  } else o2 = e2.pages[i2.selection - 1].map((t2) => t2.row);
  else o2 = e2.data.data;
  const r2 = o2.map((t2) => t2.cells.filter((t3, e3) => a2(e3)).map((t3) => n(t3))), l2 = e2.data.headings.filter((t2, e3) => a2(e3)).map((t2) => t2.text ?? String(t2.data));
  if (r2.length) {
    const t2 = [];
    r2.forEach((e4, s3) => {
      t2[s3] = t2[s3] || {}, e4.forEach((e5, i3) => {
        t2[s3][l2[i3]] = e5;
      });
    });
    const e3 = JSON.stringify(t2, i2.replacer, i2.space);
    if (i2.download) {
      const t3 = new Blob([e3], { type: "data:application/json;charset=utf-8" }), s3 = URL.createObjectURL(t3), n2 = document.createElement("a");
      n2.href = s3, n2.download = `${i2.filename || "datatable_export"}.json`, document.body.appendChild(n2), n2.click(), document.body.removeChild(n2), URL.revokeObjectURL(s3);
    }
    return e3;
  }
  return false;
};
var ct = function(e2, s2 = {}) {
  if (!e2.hasHeadings && !e2.hasRows) return false;
  if (!t(s2)) return false;
  const i2 = { download: true, skipColumn: [], tableName: "myTable", ...s2 }, a2 = (t2) => {
    var _a;
    return !i2.skipColumn.includes(t2) && !((_a = e2.columns.settings[t2]) == null ? void 0 : _a.hidden);
  };
  let o2 = [];
  if (i2.selection) if (Array.isArray(i2.selection)) for (let t2 = 0; t2 < i2.selection.length; t2++) o2 = o2.concat(e2.pages[i2.selection[t2] - 1].map((t3) => t3.row));
  else o2 = e2.pages[i2.selection - 1].map((t2) => t2.row);
  else o2 = e2.data.data;
  const r2 = o2.map((t2) => t2.cells.filter((t3, e3) => a2(e3)).map((t3) => n(t3))), l2 = e2.data.headings.filter((t2, e3) => a2(e3)).map((t2) => t2.text ?? String(t2.data));
  if (r2.length) {
    let t2 = `INSERT INTO \`${i2.tableName}\` (`;
    if (l2.forEach((e3) => {
      t2 += `\`${e3}\`,`;
    }), t2 = t2.trim().substring(0, t2.length - 1), t2 += ") VALUES ", r2.forEach((e3) => {
      t2 += "(", e3.forEach((e4) => {
        t2 += "string" == typeof e4 ? `"${e4}",` : `${e4},`;
      }), t2 = t2.trim().substring(0, t2.length - 1), t2 += "),";
    }), t2 = t2.trim().substring(0, t2.length - 1), t2 += ";", i2.download && (t2 = `data:application/sql;charset=utf-8,${t2}`), i2.download) {
      const e3 = document.createElement("a");
      e3.href = encodeURI(t2), e3.download = `${i2.filename || "datatable_export"}.sql`, document.body.appendChild(e3), e3.click(), document.body.removeChild(e3);
    }
    return t2;
  }
  return false;
};
var ht = function(e2, s2 = {}) {
  if (!e2.hasHeadings && !e2.hasRows) return false;
  if (!t(s2)) return false;
  const i2 = { download: true, skipColumn: [], lineDelimiter: "\n", columnDelimiter: ",", ...s2 }, a2 = (t2) => {
    var _a;
    return !i2.skipColumn.includes(t2) && !((_a = e2.columns.settings[t2]) == null ? void 0 : _a.hidden);
  }, o2 = e2.data.headings.filter((t2, e3) => a2(e3)).map((t2) => t2.text ?? t2.data);
  let r2;
  if (i2.selection) if (Array.isArray(i2.selection)) {
    r2 = [];
    for (let t2 = 0; t2 < i2.selection.length; t2++) r2 = r2.concat(e2.pages[i2.selection[t2] - 1].map((t3) => t3.row));
  } else r2 = e2.pages[i2.selection - 1].map((t2) => t2.row);
  else r2 = e2.data.data;
  let l2 = [];
  if (l2[0] = o2, l2 = l2.concat(r2.map((t2) => t2.cells.filter((t3, e3) => a2(e3)).map((t3) => n(t3)))), l2.length) {
    let t2 = "";
    if (l2.forEach((e3) => {
      e3.forEach((e4) => {
        "string" == typeof e4 && (e4 = (e4 = (e4 = (e4 = (e4 = e4.trim()).replace(/\s{2,}/g, " ")).replace(/\n/g, "  ")).replace(/"/g, '""')).replace(/#/g, "%23")).includes(",") && (e4 = `"${e4}"`), t2 += e4 + i2.columnDelimiter;
      }), t2 = t2.trim().substring(0, t2.length - 1), t2 += i2.lineDelimiter;
    }), t2 = t2.trim().substring(0, t2.length - 1), i2.download && (t2 = `data:text/csv;charset=utf-8,${t2}`), i2.download) {
      const e3 = document.createElement("a");
      e3.href = encodeURI(t2), e3.download = `${i2.filename || "datatable_export"}.txt`, document.body.appendChild(e3), e3.click(), document.body.removeChild(e3);
    }
    return t2;
  }
  return false;
};
var ut = { classes: { row: "datatable-editor-row", form: "datatable-editor-form", item: "datatable-editor-item", menu: "datatable-editor-menu", save: "datatable-editor-save", block: "datatable-editor-block", cancel: "datatable-editor-cancel", close: "datatable-editor-close", inner: "datatable-editor-inner", input: "datatable-editor-input", label: "datatable-editor-label", modal: "datatable-editor-modal", action: "datatable-editor-action", header: "datatable-editor-header", wrapper: "datatable-editor-wrapper", editable: "datatable-editor-editable", container: "datatable-editor-container", separator: "datatable-editor-separator" }, labels: { closeX: "x", editCell: "Edit Cell", editRow: "Edit Row", removeRow: "Remove Row", reallyRemove: "Are you sure?", reallyCancel: "Do you really want to cancel?", save: "Save", cancel: "Cancel" }, cancelModal: (t2) => confirm(t2.options.labels.reallyCancel), inline: true, hiddenColumns: false, contextMenu: true, clickEvent: "dblclick", excludeColumns: [], menuItems: [{ text: (t2) => t2.options.labels.editCell, action: (t2, e2) => {
  if (!(t2.event.target instanceof Element)) return;
  const s2 = t2.event.target.closest("td");
  return t2.editCell(s2);
} }, { text: (t2) => t2.options.labels.editRow, action: (t2, e2) => {
  if (!(t2.event.target instanceof Element)) return;
  const s2 = t2.event.target.closest("tr");
  return t2.editRow(s2);
} }, { separator: true }, { text: (t2) => t2.options.labels.removeRow, action: (t2, e2) => {
  if (t2.event.target instanceof Element && confirm(t2.options.labels.reallyRemove)) {
    const e3 = t2.event.target.closest("tr");
    t2.removeRow(e3);
  }
} }] };
var pt = class {
  constructor(t2, e2 = {}) {
    __publicField(this, "menuOpen");
    __publicField(this, "containerDOM");
    __publicField(this, "data");
    __publicField(this, "disabled");
    __publicField(this, "dt");
    __publicField(this, "editing");
    __publicField(this, "editingCell");
    __publicField(this, "editingRow");
    __publicField(this, "event");
    __publicField(this, "events");
    __publicField(this, "initialized");
    __publicField(this, "limits");
    __publicField(this, "menuDOM");
    __publicField(this, "modalDOM");
    __publicField(this, "options");
    __publicField(this, "originalRowRender");
    __publicField(this, "rect");
    __publicField(this, "wrapperDOM");
    this.dt = t2, this.options = { ...ut, ...e2 };
  }
  init() {
    var _a;
    this.initialized || ((_a = this.options.classes.editable) == null ? void 0 : _a.split(" ").forEach((t2) => this.dt.wrapperDOM.classList.add(t2)), this.options.inline && (this.originalRowRender = this.dt.options.rowRender, this.dt.options.rowRender = (t2, e2, s2) => {
      let i2 = this.rowRender(t2, e2, s2);
      return this.originalRowRender && (i2 = this.originalRowRender(t2, i2, s2)), i2;
    }), this.options.contextMenu && (this.containerDOM = s("div", { id: this.options.classes.container }), this.wrapperDOM = s("div", { class: this.options.classes.wrapper }), this.menuDOM = s("ul", { class: this.options.classes.menu }), this.options.menuItems && this.options.menuItems.length && this.options.menuItems.forEach((t2) => {
      const e2 = s("li", { class: t2.separator ? this.options.classes.separator : this.options.classes.item });
      if (!t2.separator) {
        const i2 = s("a", { class: this.options.classes.action, href: t2.url || "#", html: "function" == typeof t2.text ? t2.text(this) : t2.text });
        e2.appendChild(i2), t2.action && "function" == typeof t2.action && i2.addEventListener("click", (e3) => {
          e3.preventDefault(), t2.action(this, e3);
        });
      }
      this.menuDOM.appendChild(e2);
    }), this.wrapperDOM.appendChild(this.menuDOM), this.containerDOM.appendChild(this.wrapperDOM), this.updateMenu()), this.data = {}, this.menuOpen = false, this.editing = false, this.editingRow = false, this.editingCell = false, this.bindEvents(), setTimeout(() => {
      this.initialized = true, this.dt.emit("editable.init");
    }, 10));
  }
  bindEvents() {
    this.events = { keydown: this.keydown.bind(this), click: this.click.bind(this) }, this.dt.dom.addEventListener(this.options.clickEvent, this.events.click), document.addEventListener("keydown", this.events.keydown), this.options.contextMenu && (this.events.context = this.context.bind(this), this.events.updateMenu = this.updateMenu.bind(this), this.events.dismissMenu = this.dismissMenu.bind(this), this.events.reset = h(() => this.events.updateMenu(), 50), this.dt.dom.addEventListener("contextmenu", this.events.context), document.addEventListener("click", this.events.dismissMenu), window.addEventListener("resize", this.events.reset), window.addEventListener("scroll", this.events.reset));
  }
  context(t2) {
    const e2 = t2.target;
    if (!(e2 instanceof Element)) return;
    this.event = t2;
    const s2 = e2.closest("tbody td");
    if (!this.disabled && s2) {
      t2.preventDefault();
      let e3 = t2.pageX, s3 = t2.pageY;
      e3 > this.limits.x && (e3 -= this.rect.width), s3 > this.limits.y && (s3 -= this.rect.height), this.wrapperDOM.style.top = `${s3}px`, this.wrapperDOM.style.left = `${e3}px`, this.openMenu(), this.updateMenu();
    }
  }
  click(t2) {
    const e2 = t2.target;
    if (e2 instanceof Element) {
      if (this.editing && this.data && this.editingCell) {
        const t3 = l(this.options.classes.input), e3 = this.modalDOM ? this.modalDOM.querySelector(`input${t3}[type=text]`) : this.dt.wrapperDOM.querySelector(`input${t3}[type=text]`);
        this.saveCell(e3.value);
      } else if (!this.editing) {
        const s2 = e2.closest("tbody td");
        s2 && (this.editCell(s2), t2.preventDefault());
      }
    }
  }
  keydown(t2) {
    const e2 = l(this.options.classes.input);
    if (this.modalDOM) {
      if ("Escape" === t2.key) this.options.cancelModal(this) && this.closeModal();
      else if ("Enter" === t2.key) if (this.editingCell) {
        const t3 = this.modalDOM.querySelector(`input${e2}[type=text]`);
        this.saveCell(t3.value);
      } else {
        const t3 = Array.from(this.modalDOM.querySelectorAll(`input${e2}[type=text]`)).map((t4) => t4.value.trim());
        this.saveRow(t3, this.data.row);
      }
    } else if (this.editing && this.data) if ("Enter" === t2.key) {
      if (this.editingCell) {
        const t3 = this.dt.wrapperDOM.querySelector(`input${e2}[type=text]`);
        this.saveCell(t3.value);
      } else if (this.editingRow) {
        const t3 = Array.from(this.dt.wrapperDOM.querySelectorAll(`input${e2}[type=text]`)).map((t4) => t4.value.trim());
        this.saveRow(t3, this.data.row);
      }
    } else "Escape" === t2.key && (this.editingCell ? this.saveCell(this.data.content) : this.editingRow && this.saveRow(null, this.data.row));
  }
  editCell(t2) {
    const e2 = o(t2.cellIndex, this.dt.columns.settings);
    if (this.options.excludeColumns.includes(e2)) return void this.closeMenu();
    const s2 = parseInt(t2.parentElement.dataset.index, 10), i2 = this.dt.data.data[s2].cells[e2];
    this.data = { cell: i2, rowIndex: s2, columnIndex: e2, content: n(i2) }, this.editing = true, this.editingCell = true, this.options.inline ? this.dt.update() : this.editCellModal(), this.closeMenu();
  }
  editCellModal() {
    const t2 = this.data.cell, e2 = this.data.columnIndex, i2 = this.dt.data.headings[e2].text || String(this.dt.data.headings[e2].data), o2 = [`<div class='${this.options.classes.inner}'>`, `<div class='${this.options.classes.header}'>`, `<h4>${this.options.labels.editCell}</h4>`, `<button class='${this.options.classes.close}' type='button' data-editor-cancel>${this.options.labels.closeX}</button>`, " </div>", `<div class='${this.options.classes.block}'>`, `<form class='${this.options.classes.form}'>`, `<div class='${this.options.classes.row}'>`, `<label class='${this.options.classes.label}'>${a(i2)}</label>`, `<input class='${this.options.classes.input}' value='${a(n(t2))}' type='text'>`, "</div>", `<div class='${this.options.classes.row}'>`, `<button class='${this.options.classes.cancel}' type='button' data-editor-cancel>${this.options.labels.cancel}</button>`, `<button class='${this.options.classes.save}' type='button' data-editor-save>${this.options.labels.save}</button>`, "</div>", "</form>", "</div>", "</div>"].join(""), r2 = s("div", { class: this.options.classes.modal, html: o2 });
    this.modalDOM = r2, this.openModal();
    const d2 = l(this.options.classes.input), c2 = r2.querySelector(`input${d2}[type=text]`);
    c2.focus(), c2.selectionStart = c2.selectionEnd = c2.value.length, r2.addEventListener("click", (t3) => {
      const e3 = t3.target;
      e3 instanceof Element && (e3.hasAttribute("data-editor-cancel") ? (t3.preventDefault(), this.options.cancelModal(this) && this.closeModal()) : e3.hasAttribute("data-editor-save") && (t3.preventDefault(), this.saveCell(c2.value)));
    });
  }
  saveCell(t2) {
    const e2 = this.data.content, s2 = this.dt.columns.settings[this.data.columnIndex].type || this.dt.options.type, i2 = t2.trim();
    let n2;
    if ("number" === s2) n2 = { data: parseFloat(i2) };
    else if ("boolean" === s2) n2 = ["", "false", "0"].includes(i2) ? { data: false, text: "false", order: 0 } : { data: true, text: "true", order: 1 };
    else if ("html" === s2) n2 = { data: [{ nodeName: "#text", data: t2 }], text: t2, order: t2 };
    else if ("string" === s2) n2 = { data: t2 };
    else if ("date" === s2) {
      const e3 = this.dt.columns.settings[this.data.columnIndex].format || this.dt.options.format;
      n2 = { data: t2, order: X(String(t2), e3) };
    } else n2 = { data: t2 };
    this.dt.data.data[this.data.rowIndex].cells[this.data.columnIndex] = n2, this.closeModal();
    const a2 = this.data.rowIndex, o2 = this.data.columnIndex;
    this.data = {}, this.dt.update(true), this.editing = false, this.editingCell = false, this.dt.emit("editable.save.cell", t2, e2, a2, o2);
  }
  editRow(t2) {
    if (!t2 || "TR" !== t2.nodeName || this.editing) return;
    const e2 = parseInt(t2.dataset.index, 10), s2 = this.dt.data.data[e2];
    this.data = { row: s2.cells, rowIndex: e2 }, this.editing = true, this.editingRow = true, this.options.inline ? this.dt.update() : this.editRowModal(), this.closeMenu();
  }
  editRowModal() {
    var _a;
    const t2 = this.data.row, e2 = [`<div class='${this.options.classes.inner}'>`, `<div class='${this.options.classes.header}'>`, `<h4>${this.options.labels.editRow}</h4>`, `<button class='${this.options.classes.close}' type='button' data-editor-cancel>${this.options.labels.closeX}</button>`, " </div>", `<div class='${this.options.classes.block}'>`, `<form class='${this.options.classes.form}'>`, `<div class='${this.options.classes.row}'>`, `<button class='${this.options.classes.cancel}' type='button' data-editor-cancel>${this.options.labels.cancel}</button>`, `<button class='${this.options.classes.save}' type='button' data-editor-save>${this.options.labels.save}</button>`, "</div>", "</form>", "</div>", "</div>"].join(""), i2 = s("div", { class: this.options.classes.modal, html: e2 }), o2 = i2.firstElementChild;
    if (!o2) return;
    const r2 = (_a = o2.lastElementChild) == null ? void 0 : _a.firstElementChild;
    if (!r2) return;
    t2.forEach((t3, e3) => {
      const i3 = this.dt.columns.settings[e3];
      if ((!i3.hidden || i3.hidden && this.options.hiddenColumns) && !this.options.excludeColumns.includes(e3)) {
        const i4 = this.dt.data.headings[e3].text || String(this.dt.data.headings[e3].data);
        r2.insertBefore(s("div", { class: this.options.classes.row, html: [`<div class='${this.options.classes.row}'>`, `<label class='${this.options.classes.label}'>${a(i4)}</label>`, `<input class='${this.options.classes.input}' value='${a(n(t3))}' type='text'>`, "</div>"].join("") }), r2.lastElementChild);
      }
    }), this.modalDOM = i2, this.openModal();
    const d2 = l(this.options.classes.input), c2 = Array.from(r2.querySelectorAll(`input${d2}[type=text]`));
    i2.addEventListener("click", (t3) => {
      const e3 = t3.target;
      if (e3 instanceof Element) {
        if (e3.hasAttribute("data-editor-cancel")) this.options.cancelModal(this) && this.closeModal();
        else if (e3.hasAttribute("data-editor-save")) {
          const t4 = c2.map((t5) => t5.value.trim());
          this.saveRow(t4, this.data.row);
        }
      }
    });
  }
  saveRow(t2, e2) {
    const s2 = e2.map((t3) => n(t3)), i2 = this.dt.data.data[this.data.rowIndex];
    if (t2) {
      let s3 = 0;
      i2.cells = e2.map((e3, i3) => {
        if (this.options.excludeColumns.includes(i3) || this.dt.columns.settings[i3].hidden) return e3;
        const n2 = this.dt.columns.settings[i3].type || this.dt.options.type, a3 = t2[s3++];
        let o2;
        if ("number" === n2) o2 = { data: parseFloat(a3) };
        else if ("boolean" === n2) o2 = ["", "false", "0"].includes(a3) ? { data: false, text: "false", order: 0 } : { data: true, text: "true", order: 1 };
        else if ("html" === n2) o2 = { data: [{ nodeName: "#text", data: a3 }], text: a3, order: a3 };
        else if ("string" === n2) o2 = { data: a3 };
        else if ("date" === n2) {
          const t3 = this.dt.columns.settings[i3].format || this.dt.options.format;
          o2 = { data: a3, order: X(String(a3), t3) };
        } else o2 = { data: a3 };
        return o2;
      });
    }
    const a2 = i2.cells.map((t3) => n(t3));
    this.data = {}, this.dt.update(true), this.closeModal(), this.editing = false, this.dt.emit("editable.save.row", a2, s2, e2);
  }
  openModal() {
    this.modalDOM && document.body.appendChild(this.modalDOM);
  }
  closeModal() {
    this.editing && this.modalDOM && (document.body.removeChild(this.modalDOM), this.modalDOM = this.editing = this.editingRow = this.editingCell = false);
  }
  removeRow(t2) {
    if (!t2 || "TR" !== t2.nodeName || this.editing) return;
    const e2 = parseInt(t2.dataset.index, 10);
    this.dt.rows.remove(e2), this.closeMenu();
  }
  updateMenu() {
    const t2 = window.scrollX || window.pageXOffset, e2 = window.scrollY || window.pageYOffset;
    this.rect = this.wrapperDOM.getBoundingClientRect(), this.limits = { x: window.innerWidth + t2 - this.rect.width, y: window.innerHeight + e2 - this.rect.height };
  }
  dismissMenu(t2) {
    const e2 = t2.target;
    if (!(e2 instanceof Element) || this.wrapperDOM.contains(e2)) return;
    let s2 = true;
    if (this.editing) {
      const t3 = l(this.options.classes.input);
      s2 = !e2.matches(`input${t3}[type=text]`);
    }
    s2 && this.closeMenu();
  }
  openMenu() {
    if (this.editing && this.data && this.editingCell) {
      const t2 = l(this.options.classes.input), e2 = this.modalDOM ? this.modalDOM.querySelector(`input${t2}[type=text]`) : this.dt.wrapperDOM.querySelector(`input${t2}[type=text]`);
      this.saveCell(e2.value);
    }
    document.body.appendChild(this.containerDOM), this.menuOpen = true, this.dt.emit("editable.context.open");
  }
  closeMenu() {
    this.menuOpen && (this.menuOpen = false, document.body.removeChild(this.containerDOM), this.dt.emit("editable.context.close"));
  }
  destroy() {
    this.dt.dom.removeEventListener(this.options.clickEvent, this.events.click), this.dt.dom.removeEventListener("contextmenu", this.events.context), document.removeEventListener("click", this.events.dismissMenu), document.removeEventListener("keydown", this.events.keydown), window.removeEventListener("resize", this.events.reset), window.removeEventListener("scroll", this.events.reset), document.body.contains(this.containerDOM) && document.body.removeChild(this.containerDOM), this.options.inline && (this.dt.options.rowRender = this.originalRowRender), this.initialized = false;
  }
  rowRender(t2, e2, s2) {
    if (!this.data || this.data.rowIndex !== s2) return e2;
    if (this.editingCell) {
      e2.childNodes[function(t3, e3) {
        let s3 = t3, i2 = 0;
        for (; i2 < t3; ) e3[i2].hidden && (s3 -= 1), i2++;
        return s3;
      }(this.data.columnIndex, this.dt.columns.settings)].childNodes = [{ nodeName: "INPUT", attributes: { type: "text", value: this.data.content, class: this.options.classes.input } }];
    } else e2.childNodes.forEach((s3, i2) => {
      const n2 = o(i2, this.dt.columns.settings), r2 = t2[n2];
      if (!this.options.excludeColumns.includes(n2)) {
        e2.childNodes[i2].childNodes = [{ nodeName: "INPUT", attributes: { type: "text", value: a(r2.text || String(r2.data) || ""), class: this.options.classes.input } }];
      }
    });
    return e2;
  }
};
var ft = function(t2, e2 = {}) {
  const s2 = new pt(t2, e2);
  return t2.initialized ? s2.init() : t2.on("datatable.init", () => s2.init()), s2;
};
var mt = { classes: { button: "datatable-column-filter-button", menu: "datatable-column-filter-menu", container: "datatable-column-filter-container", wrapper: "datatable-column-filter-wrapper" }, labels: { button: "Filter columns within the table" }, hiddenColumns: [] };
var gt = class {
  constructor(t2, e2 = {}) {
    __publicField(this, "addedButtonDOM");
    __publicField(this, "menuOpen");
    __publicField(this, "buttonDOM");
    __publicField(this, "dt");
    __publicField(this, "events");
    __publicField(this, "initialized");
    __publicField(this, "options");
    __publicField(this, "menuDOM");
    __publicField(this, "containerDOM");
    __publicField(this, "wrapperDOM");
    __publicField(this, "limits");
    __publicField(this, "rect");
    __publicField(this, "event");
    this.dt = t2, this.options = { ...mt, ...e2 };
  }
  init() {
    if (this.initialized) return;
    const t2 = l(this.options.classes.button);
    let e2 = this.dt.wrapperDOM.querySelector(t2);
    if (!e2) {
      e2 = s("button", { class: this.options.classes.button, html: "▦" });
      const t3 = l(this.dt.options.classes.search), i2 = this.dt.wrapperDOM.querySelector(t3);
      i2 ? i2.appendChild(e2) : this.dt.wrapperDOM.appendChild(e2), this.addedButtonDOM = true;
    }
    this.buttonDOM = e2, this.containerDOM = s("div", { id: this.options.classes.container }), this.wrapperDOM = s("div", { class: this.options.classes.wrapper }), this.menuDOM = s("ul", { class: this.options.classes.menu, html: this.dt.data.headings.map((t3, e3) => {
      const s2 = this.dt.columns.settings[e3];
      return this.options.hiddenColumns.includes(e3) ? "" : `<li data-column="${e3}">
                        <input type="checkbox" value="${t3.text || t3.data}" ${s2.hidden ? "" : "checked=''"}>
                        <label>
                            ${t3.text || t3.data}
                        </label>
                    </li>`;
    }).join("") }), this.wrapperDOM.appendChild(this.menuDOM), this.containerDOM.appendChild(this.wrapperDOM), this._measureSpace(), this._bind(), this.initialized = true;
  }
  dismiss() {
    this.addedButtonDOM && this.buttonDOM.parentElement && this.buttonDOM.parentElement.removeChild(this.buttonDOM), document.removeEventListener("click", this.events.click);
  }
  _bind() {
    this.events = { click: this._click.bind(this) }, document.addEventListener("click", this.events.click);
  }
  _openMenu() {
    document.body.appendChild(this.containerDOM), this._measureSpace(), this.menuOpen = true, this.dt.emit("columnFilter.menu.open");
  }
  _closeMenu() {
    this.menuOpen && (this.menuOpen = false, document.body.removeChild(this.containerDOM), this.dt.emit("columnFilter.menu.close"));
  }
  _measureSpace() {
    const t2 = window.scrollX || window.pageXOffset, e2 = window.scrollY || window.pageYOffset;
    this.rect = this.wrapperDOM.getBoundingClientRect(), this.limits = { x: window.innerWidth + t2 - this.rect.width, y: window.innerHeight + e2 - this.rect.height };
  }
  _click(t2) {
    const e2 = t2.target;
    if (e2 instanceof Element) if (this.event = t2, this.buttonDOM.contains(e2)) {
      if (t2.preventDefault(), this.menuOpen) return void this._closeMenu();
      this._openMenu();
      let e3 = t2.pageX, s2 = t2.pageY;
      e3 > this.limits.x && (e3 -= this.rect.width), s2 > this.limits.y && (s2 -= this.rect.height), this.wrapperDOM.style.top = `${s2}px`, this.wrapperDOM.style.left = `${e3}px`;
    } else if (this.menuDOM.contains(e2)) {
      const t3 = l(this.options.classes.menu), s2 = e2.closest(`${t3} > li`);
      if (!s2) return;
      const i2 = s2.querySelector("input[type=checkbox]");
      i2.contains(e2) || (i2.checked = !i2.checked);
      const n2 = Number(s2.dataset.column);
      i2.checked ? this.dt.columns.show([n2]) : this.dt.columns.hide([n2]);
    } else this.menuOpen && this._closeMenu();
  }
};
var bt = function(t2, e2 = {}) {
  const s2 = new gt(t2, e2);
  return t2.initialized ? s2.init() : t2.on("datatable.init", () => s2.init()), s2;
};
export {
  at as DataTable,
  bt as addColumnFilter,
  ot as convertCSV,
  rt as convertJSON,
  s as createElement,
  lt as exportCSV,
  dt as exportJSON,
  ct as exportSQL,
  ht as exportTXT,
  e as isJson,
  t as isObject,
  ft as makeEditable
};
//# sourceMappingURL=simple-datatables.js.map
