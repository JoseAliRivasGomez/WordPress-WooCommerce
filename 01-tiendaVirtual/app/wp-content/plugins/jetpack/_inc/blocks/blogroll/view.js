(()=>{var e={80425:(e,t,r)=>{"object"==typeof window&&window.Jetpack_Block_Assets_Base_Url&&(r.p=window.Jetpack_Block_Assets_Base_Url)}},t={};function r(o){var c=t[o];if(void 0!==c)return c.exports;var n=t[o]={exports:{}};return e[o](n,n.exports,r),n.exports}r.n=e=>{var t=e&&e.__esModule?()=>e.default:()=>e;return r.d(t,{a:t}),t},r.d=(e,t)=>{for(var o in t)r.o(t,o)&&!r.o(e,o)&&Object.defineProperty(e,o,{enumerable:!0,get:t[o]})},r.g=function(){if("object"==typeof globalThis)return globalThis;try{return this||new Function("return this")()}catch(e){if("object"==typeof window)return window}}(),r.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),(()=>{var e;r.g.importScripts&&(e=r.g.location+"");var t=r.g.document;if(!e&&t&&(t.currentScript&&(e=t.currentScript.src),!e)){var o=t.getElementsByTagName("script");o.length&&(e=o[o.length-1].src)}if(!e)throw new Error("Automatic publicPath is not supported in this browser");e=e.replace(/#.*$/,"").replace(/\?.*$/,"").replace(/\/[^\/]+$/,"/"),r.p=e+"../"})(),(()=>{"use strict";r(80425)})(),(()=>{"use strict";function e(e){e.preventDefault();const t=e.currentTarget.closest(".wp-block-jetpack-blogroll-item");t?.classList.toggle("open")?t.querySelector(".jetpack-blogroll-item-submit").removeAttribute("disabled"):t.querySelector(".jetpack-blogroll-item-submit").setAttribute("disabled","disabled")}document.addEventListener("DOMContentLoaded",(function(){document.querySelectorAll(".jetpack-blogroll-item-subscribe-button, .jetpack-blogroll-item-cancel-button").forEach((t=>{t.addEventListener("click",e)}))}))})()})();