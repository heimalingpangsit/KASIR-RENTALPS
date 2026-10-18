(function(){var n=document.querySelector('[data-now]'),off=n?(+n.dataset.now)-Date.now()/1000:0;
function f(s){s=Math.floor(s);return[Math.floor(s/3600),Math.floor(s%3600/60),s%60].map(function(x){return String(x).padStart(2,'0')}).join(':')}
function tick(){var now=Date.now()/1000+off;document.querySelectorAll('[data-end]').forEach(function(el){var l=(+el.dataset.end)-now;el.textContent=l>0?f(l):'Waktu habis';el.classList.toggle('over',l<=0);var r=el.closest('[data-total]');if(r){var c=r.querySelector('.ring-fg');if(c)c.style.strokeDashoffset=565.5*(1-Math.max(0,Math.min(1,l/(+r.dataset.total))))}})}
tick();setInterval(tick,1000);
var tot=document.getElementById('sum');if(tot){var rec=function(){var t=0,q=0;document.querySelectorAll('.stp').forEach(function(s){var v=+s.querySelector('input').value;q+=v;t+=v*(+s.dataset.price)});tot.textContent='Rp '+t.toLocaleString('id-ID');document.getElementById('cnt').textContent=q+' item';document.getElementById('go').disabled=!q};
document.querySelectorAll('.stp').forEach(function(s){var i=s.querySelector('input');s.addEventListener('click',function(e){var d=+e.target.dataset.d;if(!d)return;i.value=Math.max(0,Math.min(20,(+i.value)+d));rec()})});rec()}
var q=document.getElementById('q');if(q)q.addEventListener('input',function(){var v=q.value.toLowerCase();document.querySelectorAll('[data-s]').forEach(function(r){r.style.display=r.dataset.s.indexOf(v)<0?'none':''})});
function bindUi(r){r=r||document;
r.querySelectorAll('[data-confirm]').forEach(function(f){f.addEventListener('submit',function(e){if(!confirm(f.dataset.confirm))e.preventDefault()})});
r.querySelectorAll('[data-dlg]').forEach(function(b){b.addEventListener('click',function(){var d=document.getElementById(b.dataset.dlg);if(d&&d.showModal)d.showModal()})});
r.querySelectorAll('dialog').forEach(function(d){d.addEventListener('click',function(e){if(e.target===d||e.target.hasAttribute('data-close'))d.close()})})}
bindUi();window.bindUi=bindUi;
})();
