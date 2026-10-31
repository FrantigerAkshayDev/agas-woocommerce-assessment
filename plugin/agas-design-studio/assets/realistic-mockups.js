(function () {
  'use strict';
  /*
   * Mockup routing is about physical form AND use-case, not product alone.
   * Every scene owns its own photographic background and artwork placement.
   * Print exports never use this canvas; it is an illustrative customer preview.
   */
  var sources = Object.create(null);
  var work = new WeakMap();
  var GRID = 13;
  var SCENES = Object.freeze({
    'banner-storefront': {kind:'banner', photo:'banner.jpg', label:'Storefront vinyl banner', quad:[[112,115],[889,280],[890,508],[104,418]], folds:1.5},
    'banner-event': {kind:'banner', photo:'banner-event.jpg', label:'Outdoor event banner', quad:[[100,177],[803,178],[812,417],[93,421]], folds:2.0},
    'flag-feather': {kind:'flag', photo:'flag.jpg', label:'Business feather flag', feather:true},
    'flag-rectangular': {kind:'flag', photo:'flag-rectangular.jpg', label:'Rectangular country flag outside a civic plaza', quad:[[298,154],[1156,313],[1215,788],[296,692]], folds:4.2, fabric:true},
    'sign-lawn': {kind:'sign', photo:'sign.jpg', label:'Real-estate lawn sign', quad:[[134,92],[459,55],[459,258],[135,282]], folds:0},
    'sign-parking': {kind:'sign', photo:'sign-parking.jpg', label:'Metal parking sign', quad:[[294,122],[630,122],[630,341],[294,341]], folds:0},
    'sign-event': {kind:'sign', photo:'sign-event.jpg', label:'Event directional yard sign', quad:[[214,146],[733,87],[734,409],[216,445]], folds:0}
  });
  var FALLBACK = {banner:'banner-storefront',flag:'flag-feather',sign:'sign-lawn'};

  function resolve(kind, category, title, preferred) {
    kind = String(kind || 'banner').toLowerCase();
    if (!FALLBACK[kind]) kind = 'banner';
    preferred = String(preferred || '').toLowerCase();
    if (SCENES[preferred] && SCENES[preferred].kind === kind) return preferred;
    var cat = String(category || '').toLowerCase();
    var name = String(title || '').toLowerCase();
    if (kind === 'flag') {
      return /country|nation|national|heritage|world|state flags?/.test(cat) ||
        /\b(france|japan|italy|ukraine|india|germany|country)\b/.test(name)
        ? 'flag-rectangular' : 'flag-feather';
    }
    if (kind === 'banner') {
      if (/grand opening|new opening/.test(name)) return 'banner-storefront';
      return /events?|community|festival|fundraiser|safety/.test(cat) ||
        /festival|fundraiser|concert|community event|safety/.test(name)
        ? 'banner-event' : 'banner-storefront';
    }
    if (/parking|directions?|safety|construction/.test(cat) || /reserved|parking|caution/.test(name)) return 'sign-parking';
    if (/events?|wayfinding/.test(cat) || /event entrance|event parking|festival/.test(name)) return 'sign-event';
    return 'sign-lawn';
  }

  function configure(stage, details) {
    if (!stage) return null;
    details = details || {};
    var kind = details.kind || stage.getAttribute('data-mockup-kind') || 'banner';
    var profile = resolve(kind, details.category || '', details.name || '', details.override || '');
    stage.setAttribute('data-mockup-profile',profile);
    var definition = SCENES[profile];
    var base = stage.getAttribute('data-mockup-base') || '';
    if (base && !base.endsWith('/')) base += '/';
    if (base) stage.setAttribute('data-mockup-photo',base+definition.photo);
    return {profile:profile,label:definition.label};
  }

  function image(src) {
    if (!src) return Promise.reject(new Error('Missing image source'));
    if (sources[src]) return sources[src];
    sources[src] = new Promise(function (resolveImage, reject) {
      var img = new Image();
      img.decoding='async';
      img.onload=function(){resolveImage(img);};
      img.onerror=function(){reject(new Error('Image could not be loaded'));};
      // Mockups remain visual-only. Nothing from this canvas is exported to PDF.
      img.src=src;
    }).catch(function (err) {delete sources[src];throw err;});
    return sources[src];
  }

  function point(scene,u,v) {
    if (scene.fabric) {
      // Control points follow the naturally billowing edges of the approved
      // country-flag photograph. Country-flag art remains landscape, never
      // rotated like the portrait artwork on a feather flag.
      var top = [[298,154],[460,165],[654,197],[881,242],[1156,313]];
      var bottom = [[296,692],[460,710],[654,727],[881,760],[1215,788]];
      function sample(points,t) {
        var v0=Math.min(3,Math.floor(t*4)),f=t*4-v0;
        if(t>=1){v0=3;f=1;}
        return {x:points[v0][0]*(1-f)+points[v0+1][0]*f,
          y:points[v0][1]*(1-f)+points[v0+1][1]*f};
      }
      var t=sample(top,u),b=sample(bottom,u);
      var billow=scene.folds*Math.sin(4*Math.PI*u+v*Math.PI)*Math.sin(Math.PI*v);
      return {x:t.x*(1-v)+b.x*v,y:t.y*(1-v)+b.y*v+billow};
    }
    if (scene.feather) {
      // A vertical, curved feather-shaped display, never used for national flags.
      var curve=Math.min(1,Math.max(0,v*5));
      var left=430-163*Math.sin(curve*Math.PI/2);
      var right=431-7*v;
      return {x:left+(right-left)*u,y:(9+467*v)*(1-u)+(9+414*v)*u};
    }
    var q=scene.quad;
    var topX=q[0][0]*(1-u)+q[1][0]*u;
    var topY=q[0][1]*(1-u)+q[1][1]*u;
    var bottomX=q[3][0]*(1-u)+q[2][0]*u;
    var bottomY=q[3][1]*(1-u)+q[2][1]*u;
    var ripple=(scene.folds||0)*Math.sin(u*Math.PI*4)*Math.sin(v*Math.PI);
    return {x:topX*(1-v)+bottomX*v, y:topY*(1-v)+bottomY*v+ripple};
  }

  function clipPath(ctx,scene) {
    ctx.beginPath();
    if (scene.fabric) {
      // Inset the artwork within the natural hems, keeping pole hardware,
      // border stitches and the irregular waving fly edge photographic.
      ctx.moveTo(296,152);
      ctx.bezierCurveTo(493,174,917,254,1157,307);
      ctx.bezierCurveTo(1145,354,1180,386,1184,433);
      ctx.bezierCurveTo(1171,499,1192,551,1203,591);
      ctx.bezierCurveTo(1198,672,1225,735,1221,786);
      ctx.bezierCurveTo(1001,775,786,743,557,720);
      ctx.bezierCurveTo(446,706,346,702,292,695);
      ctx.closePath();
      return;
    }
    if (scene.feather) {
      ctx.moveTo(430,9);
      ctx.bezierCurveTo(333,19,284,82,271,199);
      ctx.lineTo(265,477);
      ctx.bezierCurveTo(310,463,378,434,425,423);
      ctx.closePath();
      return;
    }
    // Clip slightly outside the mapped area; the physical substrate already
    // contains trim and framing, so graphic edges need not cover the fixtures.
    var quad=scene.quad;
    quad.forEach(function(p,i){if(!i)ctx.moveTo(p[0],p[1]);else ctx.lineTo(p[0],p[1]);});
    ctx.closePath();
  }

  function triangle(ctx,src,points,targets) {
    var a=points[0],b=points[1],c=points[2];
    var p=targets[0],q=targets[1],r=targets[2];
    var du=b.x-a.x,dv=b.y-a.y,eu=c.x-a.x,ev=c.y-a.y;
    var det=du*ev-eu*dv;
    if(Math.abs(det)<1e-8)return;
    var xx=((q.x-p.x)*ev-(r.x-p.x)*dv)/det;
    var xy=(du*(r.x-p.x)-eu*(q.x-p.x))/det;
    var yx=((q.y-p.y)*ev-(r.y-p.y)*dv)/det;
    var yy=(du*(r.y-p.y)-eu*(q.y-p.y))/det;
    var dx=p.x-xx*a.x-xy*a.y,dy=p.y-yx*a.x-yy*a.y;
    var mx=(p.x+q.x+r.x)/3,my=(p.y+q.y+r.y)/3;
    function extend(t){var ex=t.x-mx,ey=t.y-my,len=Math.hypot(ex,ey)||1;return{x:t.x+ex*1.8/len,y:t.y+ey*1.8/len};}
    var ep=extend(p),eq=extend(q),er=extend(r);
    ctx.save();ctx.beginPath();ctx.moveTo(ep.x,ep.y);ctx.lineTo(eq.x,eq.y);ctx.lineTo(er.x,er.y);ctx.closePath();ctx.clip();
    ctx.transform(xx,yx,xy,yy,dx,dy);ctx.drawImage(src,0,0);ctx.restore();
  }

  function rotateFeatherArtwork(imageObject) {
    var c=document.createElement('canvas');
    c.width=imageObject.naturalHeight||imageObject.height;
    c.height=imageObject.naturalWidth||imageObject.width;
    var ctx=c.getContext('2d');
    ctx.translate(0,c.height);ctx.rotate(-Math.PI/2);ctx.drawImage(imageObject,0,0);
    return c;
  }

  function drawArtwork(ctx,scene,artwork) {
    var source=scene.feather?rotateFeatherArtwork(artwork):artwork;
    var w=source.width||source.naturalWidth,h=source.height||source.naturalHeight;
    var nx=GRID,ny=scene.feather?32:10;
    ctx.save();clipPath(ctx,scene);ctx.clip();
    for(var j=0;j<ny;j++)for(var i=0;i<nx;i++){
      var u0=i/nx,u1=(i+1)/nx,v0=j/ny,v1=(j+1)/ny;
      var s0={x:u0*w,y:v0*h},s1={x:u1*w,y:v0*h},s2={x:u1*w,y:v1*h},s3={x:u0*w,y:v1*h};
      var p0=point(scene,u0,v0),p1=point(scene,u1,v0),p2=point(scene,u1,v1),p3=point(scene,u0,v1);
      triangle(ctx,source,[s0,s1,s2],[p0,p1,p2]);
      triangle(ctx,source,[s0,s2,s3],[p0,p2,p3]);
    }
    var start=scene.feather?260:(scene.quad?scene.quad[0][0]:100);
    var end=scene.feather?435:(scene.quad?scene.quad[1][0]:800);
    var g=ctx.createLinearGradient(start,0,end,430);
    g.addColorStop(0,'rgba(0,0,0,0.11)');g.addColorStop(.28,'rgba(255,255,255,0.10)');
    g.addColorStop(.65,'rgba(0,0,0,0.025)');g.addColorStop(1,'rgba(0,0,0,0.10)');
    ctx.fillStyle=g;ctx.fillRect(0,0,ctx.canvas.width,ctx.canvas.height);
    if(scene.folds && !scene.fabric){
      // The new cloth photograph already has natural fabric creases.
      // Avoid artificial straight fold lines across national emblems.
      ctx.strokeStyle='rgba(255,255,255,0.09)';ctx.lineWidth=2;
      for(var k=1;k<8;k++){
        var u=k/8,t=point(scene,u,0),b=point(scene,u,1);
        ctx.beginPath();ctx.moveTo(t.x,t.y);ctx.quadraticCurveTo((t.x+b.x)/2+3,(t.y+b.y)/2,b.x,b.y);ctx.stroke();
      }
    }
    ctx.restore();
  }

  function finish(ctx,scene) {
    if(scene.feather){
      ctx.beginPath();ctx.moveTo(430,9);ctx.bezierCurveTo(333,19,284,82,271,199);ctx.lineTo(265,477);
      ctx.strokeStyle='rgba(13,23,31,.82)';ctx.lineWidth=3.5;ctx.stroke();
      return;
    }
    if(scene.kind !== 'banner')return;
    [0,.25,.5,.75,1].forEach(function(u){[0,1].forEach(function(v){
      var p=point(scene,u,v);
      ctx.beginPath();ctx.arc(p.x,p.y,3.9,0,Math.PI*2);ctx.fillStyle='#cbd1d4';ctx.fill();
      ctx.beginPath();ctx.arc(p.x,p.y,2,0,Math.PI*2);ctx.fillStyle='#444746';ctx.fill();
    });});
  }

  function render(stage,artworkUrl) {
    if(!stage)return Promise.resolve(false);
    var state=work.get(stage);
    if(!state){state={token:0};work.set(stage,state);}
    var token=++state.token;
    var kind=stage.getAttribute('data-mockup-kind') || 'banner';
    var profile=stage.getAttribute('data-mockup-profile')||FALLBACK[kind]||FALLBACK.banner;
    if(!SCENES[profile]||SCENES[profile].kind!==kind)profile=FALLBACK[kind]||FALLBACK.banner;
    var photo=stage.getAttribute('data-mockup-photo')||'';
    var base=stage.getAttribute('data-mockup-base')||'';
    if(base){if(!base.endsWith('/'))base+='/';photo=base+SCENES[profile].photo;}
    if(!photo||!artworkUrl){stage.classList.remove('agas-mockup-photo-ready');return Promise.resolve(false);}
    return Promise.all([image(photo),image(artworkUrl)]).then(function(imgs){
      if(token!==state.token)return false;
      var background=imgs[0],artwork=imgs[1],scene=SCENES[profile];
      var display=stage.querySelector('.agas-mockup-photo-canvas');
      if(!display){display=document.createElement('canvas');display.className='agas-mockup-photo-canvas';display.setAttribute('role','img');stage.appendChild(display);}
      display.setAttribute('aria-label',scene.label+' displaying the selected artwork');
      display.width=background.naturalWidth||background.width;
      display.height=background.naturalHeight||background.height;
      var ctx=display.getContext('2d',{alpha:false});
      ctx.imageSmoothingEnabled=true;ctx.imageSmoothingQuality='high';
      ctx.drawImage(background,0,0);
      drawArtwork(ctx,scene,artwork);
      if(scene.fabric){
        // The original blank white fabric contains wrinkles, creases and
        // ambient light. Reapply that photographic shading over the live art.
        ctx.save();clipPath(ctx,scene);ctx.clip();
        ctx.globalCompositeOperation='multiply';
        ctx.globalAlpha=0.83;
        ctx.drawImage(background,0,0);
        ctx.restore();
      }
      finish(ctx,scene);
      stage.classList.add('agas-mockup-photo-ready');
      return true;
    }).catch(function(err){
      if(token===state.token)stage.classList.remove('agas-mockup-photo-ready');
      if(window.console&&window.console.warn)window.console.warn('AGAS mockup fallback:',err.message);
      return false;
    });
  }

  window.AGASRealisticMockups={render:render,resolve:resolve,configure:configure,scenes:SCENES};
})();
