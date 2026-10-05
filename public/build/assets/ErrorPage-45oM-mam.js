import{c,u as i,r as m,j as e,H as x,l as u,b as p}from"./app-u8zvk2_O.js";import{B as f}from"./ui-BcVbO2Po.js";/**
 * @license lucide-react v0.575.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const h=[["path",{d:"M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8",key:"1357e3"}],["path",{d:"M3 3v5h5",key:"1xhq8a"}]],j=c("rotate-ccw",h);/**
 * @license lucide-react v0.575.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const g=[["path",{d:"m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3",key:"wmoenq"}],["path",{d:"M12 9v4",key:"juzpu7"}],["path",{d:"M12 17h.01",key:"p32p05"}]],b=c("triangle-alert",g);function k({status:a,message:d}){const{t,dir:r,locale:n}=i(),s=[403,404,419,429,500,503].includes(a)?a:500;m.useEffect(()=>{document.documentElement.dir=r,document.documentElement.lang=n},[r,n]);const o=t(`error.${s}.title`),l=d||t(`error.${s}.body`);return e.jsxs("div",{dir:r,className:"grid min-h-screen place-items-center bg-background p-6",children:[e.jsx(x,{title:o}),e.jsxs("div",{className:"surface w-full max-w-md p-7 text-center",children:[e.jsx("span",{className:"mx-auto grid size-14 place-items-center rounded-2xl bg-destructive/12 text-destructive",children:e.jsx(b,{className:"size-7"})}),e.jsx("p",{className:"mt-4 text-xs font-extrabold tracking-widest text-muted-foreground",children:s}),e.jsx("h1",{className:"mt-1 text-xl font-extrabold",children:o}),e.jsx("p",{className:"mt-2 text-sm text-muted-foreground",children:l}),e.jsxs("div",{className:"mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-center",children:[e.jsx(u,{href:"/admin",className:"inline-flex items-center justify-center rounded-xl border border-border px-4 py-2 text-sm font-bold transition hover:bg-muted",children:t("error.goHome")}),e.jsxs(f,{onClick:()=>p.reload(),children:[e.jsx(j,{className:"size-4"}),t("common.retry")]})]})]})]})}export{k as default};
