// Page de présentation de Dicta. Chargé depuis l'en-tête : la classe « js » est posée avant le premier affichage.
document.documentElement.classList.add('js');
document.addEventListener('DOMContentLoaded', function () {
(function(){
  var rm=matchMedia('(prefers-reduced-motion: reduce)').matches;
  // Les sections apparaissent à la vue ; les chiffres comptent.
  var obs=document.querySelectorAll('.obs');
  function compte(el){
    if(rm)return; var c=+el.dataset.compte,t0=null;
    function pas(t){if(!t0)t0=t;var k=Math.min(1,(t-t0)/1000);k=1-Math.pow(1-k,3);el.textContent=Math.round(c*k);if(k<1)requestAnimationFrame(pas)}
    el.textContent='0'; setTimeout(function(){requestAnimationFrame(pas)},300);
  }
  if('IntersectionObserver' in window){
    var io=new IntersectionObserver(function(es){es.forEach(function(x){if(x.isIntersecting){x.target.classList.add('vu');x.target.querySelectorAll('[data-compte]').forEach(compte);io.unobserve(x.target)}})},{threshold:.12});
    obs.forEach(function(s){io.observe(s)});
  } else {obs.forEach(function(s){s.classList.add('vu')})}
  // La scène : Dicter, Vérifier, Envoyer, toute seule jusqu'au premier toucher. Avec « Réduire les animations » (iPhone),
  // elle avance aussi (Xavier, 2026-11-20 : « sur iphone la petite animation fonctionne pas tout seul »), sans mouvement :
  // la feuille coupe les animations de la scène, les étapes changent d'un coup.
  var s=document.getElementById('scene'),D=[7400,7600,6000],t=null,auto=true,tabs=s.querySelectorAll('.onglets button');
  function va(n){
    s.dataset.e=n; s.classList.toggle('auto',auto);
    tabs.forEach(function(b,i){b.setAttribute('aria-selected',i===n?'true':'false')});
    clearTimeout(t); if(auto){t=setTimeout(function(){va((n+1)%3)},D[n])}
  }
  tabs.forEach(function(b,i){b.addEventListener('click',function(){auto=false;va(i)})});
  s.querySelector('.rejouer').addEventListener('click',function(){auto=true;s.dataset.e='';void s.offsetWidth;va(0)});
  va(0);
  // Le modèle et la couleur de l'offre.
  var f=document.getElementById('feuille'),p=document.querySelectorAll('.puces button'),k=0,tour=null,tt=document.getElementById('teinte'),c=document.querySelectorAll('.teintes button');
  function modele(n){k=n;p.forEach(function(b,i){b.setAttribute('aria-pressed',i===n?'true':'false')});
    f.querySelectorAll('img').forEach(function(im,i){im.classList.toggle('on',i===n)})}
  function stop(){clearInterval(tour);tour=null}
  p.forEach(function(b,i){b.addEventListener('click',function(){stop();modele(i)})});
  c.forEach(function(b){b.addEventListener('click',function(){stop();
    c.forEach(function(x){x.setAttribute('aria-pressed',x===b?'true':'false')});
    if(b.dataset.t){tt.style.backgroundColor=b.dataset.t;tt.classList.add('on')}else{tt.classList.remove('on')}})});
  // La carte : le chantier se pose sur la ville touchée.
  var carte=document.getElementById('carte-d'),v=document.querySelectorAll('.lieu'),kv=0,tv=null,pt=document.getElementById('pt-vous'),et=document.getElementById('et-vous');
  function ville(n){kv=n;
    v.forEach(function(x,i){x.setAttribute('aria-pressed',i===n?'true':'false')});
    carte.querySelectorAll('[data-i]').forEach(function(x){x.classList.toggle('sel',+x.dataset.i===n)});
    pt.style.cssText=v[n].dataset.pos;et.style.cssText=v[n].dataset.pos;
  }
  v.forEach(function(x,i){x.addEventListener('click',function(){clearInterval(tv);tv=null;ville(i)})});
  if(!rm&&'IntersectionObserver' in window){
    new IntersectionObserver(function(es,o){if(es[0].isIntersecting){o.disconnect();tour=setInterval(function(){modele((k+1)%p.length)},2600)}},{threshold:.4}).observe(f);
    new IntersectionObserver(function(es,o){if(es[0].isIntersecting){o.disconnect();tv=setInterval(function(){ville((kv+1)%v.length)},3000)}},{threshold:.3}).observe(carte);
  }
})();
});
