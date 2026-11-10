<?php
/**
 * eurogol.ro ca aplicație (PWA), după modelul de la Poveștile Științei.
 * - manifest, iconițe, service worker (merge și fără internet: pagini văzute recent + pagina „Fără internet”);
 * - buton de instalare: Android/desktop (beforeinstallprompt), iPhone (instrucțiuni „Partajează → Adaugă pe ecranul principal”);
 * - notificări push fără conținut criptat: serverul trimite doar „trezirea”, iar aplicația ia textul de la /eg/v1/notificare.
 *   Cheile VAPID se generează pe server și stau în opțiunile WordPress (niciodată în depozit).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EG_PWA_VERSIUNE', '1' );

function eg_pwa_url( $ce ) {
	return add_query_arg( 'eg_pwa', $ce, home_url( '/' ) );
}

/* ---------- manifest, service worker, pagina offline ---------- */
add_action( 'init', function () {
	if ( empty( $_GET['eg_pwa'] ) ) {
		return;
	}
	$ce = sanitize_key( $_GET['eg_pwa'] );
	do_action( 'litespeed_control_set_nocache', 'pwa' );
	$tema = EG_URI . '/assets/pwa/';
	if ( 'manifest' === $ce ) {
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		echo wp_json_encode( array(
			'name'             => 'EuroGol',
			'short_name'       => 'EuroGol',
			'description'      => 'Fotbal românesc, european și sud-american, spus ca o poveste: știri, cronici, live și povești.',
			'id'               => '/?pwa=1',
			'start_url'        => '/?pwa=1',
			'scope'            => '/',
			'display'          => 'standalone',
			'orientation'      => 'portrait',
			'background_color' => '#0B2A6F',
			'theme_color'      => '#1546A8',
			'lang'             => 'ro',
			'categories'       => array( 'sports', 'news' ),
			'icons'            => array(
				array( 'src' => $tema . 'icon-192.png', 'sizes' => '192x192', 'type' => 'image/png' ),
				array( 'src' => $tema . 'icon-512.png', 'sizes' => '512x512', 'type' => 'image/png' ),
				array( 'src' => $tema . 'icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' ),
			),
			'shortcuts'        => array(
				array( 'name' => 'SuperLiga', 'url' => '/category/superliga/?pwa=1' ),
				array( 'name' => 'Povești', 'url' => '/category/povesti/?pwa=1' ),
			),
		), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		exit;
	}
	if ( 'sw' === $ce ) {
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Cache-Control: no-cache' );
		header( 'Service-Worker-Allowed: /' );
		$offline = eg_pwa_url( 'offline' );
		$icon    = $tema . 'icon-192.png';
		$notif   = rest_url( 'eg/v1/notificare' );
		echo "const V='eg-" . EG_PWA_VERSIUNE . "-" . esc_js( wp_get_theme()->get( 'Version' ) ) . "',OFF=" . wp_json_encode( $offline ) . ",ICON=" . wp_json_encode( $icon ) . ",NOTIF=" . wp_json_encode( $notif ) . ";\n";
		echo <<<'JS'
self.addEventListener('install',e=>{e.waitUntil(caches.open(V).then(c=>c.addAll([OFF,ICON])).then(()=>self.skipWaiting()));});
self.addEventListener('activate',e=>{e.waitUntil(caches.keys().then(k=>Promise.all(k.filter(x=>x!==V).map(x=>caches.delete(x)))).then(()=>self.clients.claim()));});
const sar=u=>u.origin!==location.origin||/\/wp-(admin|login|json)|[?&](eg_pwa|preview|nc)=|\/feed\//.test(u.pathname+u.search);
self.addEventListener('fetch',e=>{
 const r=e.request; if(r.method!=='GET')return; const u=new URL(r.url); if(sar(u))return;
 if(r.mode==='navigate'){
  e.respondWith(fetch(r).then(res=>{if(res.ok){const c=res.clone();caches.open(V).then(x=>x.put(r,c));}return res;})
   .catch(()=>caches.match(r).then(m=>m||caches.match(OFF))));
  return;
 }
 if(/\.(css|js|woff2?|png|jpe?g|webp|svg|gif)$/i.test(u.pathname)){
  e.respondWith(caches.open(V).then(c=>c.match(r).then(m=>{const f=fetch(r).then(res=>{if(res.ok)c.put(r,res.clone());return res;}).catch(()=>m);return m||f;})));
 }
});
self.addEventListener('push',e=>{
 e.waitUntil(fetch(NOTIF+'?_='+Date.now(),{cache:'no-store'}).then(r=>r.json()).catch(()=>({})).then(n=>
  self.registration.showNotification(n.titlu||'EuroGol',{body:n.text||'Avem noutăți pe EuroGol.',icon:ICON,badge:ICON,image:n.imagine||undefined,tag:n.tag||'eg',data:{url:n.url||'/'}})));
});
self.addEventListener('notificationclick',e=>{
 e.notification.close(); const url=(e.notification.data&&e.notification.data.url)||'/';
 e.waitUntil(clients.matchAll({type:'window',includeUncontrolled:true}).then(l=>{for(const c of l){if('focus' in c){c.navigate(url);return c.focus();}}return clients.openWindow(url);}));
});
JS;
		exit;
	}
	if ( 'offline' === $ce ) {
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!doctype html><html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Fără internet · EuroGol</title>'
			. '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(140deg,#1546A8,#0B2A6F);color:#fff;font-family:system-ui,sans-serif;text-align:center;padding:24px}img{width:110px}h1{font-size:26px;margin:18px 0 8px}p{opacity:.85;line-height:1.5;max-width:420px}button{margin-top:14px;padding:12px 22px;border:0;border-radius:10px;background:#fff;color:#1546A8;font-weight:700;font-size:16px}</style></head>'
			. '<body><div><img src="' . esc_url( $tema . 'icon-192.png' ) . '" alt=""><h1>Ești fără internet</h1><p>Paginile citite recent se deschid și acum. Când revine conexiunea, te așteaptă toate noutățile.</p><button onclick="location.reload()">Încearcă din nou</button></div></body></html>';
		exit;
	}
}, 0 );

/* ---------- etichetele din <head> și înregistrarea service worker-ului ---------- */
add_action( 'wp_head', function () {
	$tema = EG_URI . '/assets/pwa/';
	echo '<link rel="manifest" href="' . esc_url( eg_pwa_url( 'manifest' ) ) . '">' . "\n";
	echo '<meta name="apple-mobile-web-app-capable" content="yes"><meta name="mobile-web-app-capable" content="yes">' . "\n";
	echo '<meta name="apple-mobile-web-app-title" content="EuroGol"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' . "\n";
}, 2 );

add_action( 'wp_footer', function () {
	if ( is_admin() ) {
		return;
	}
	$cfg = array(
		'sw'     => eg_pwa_url( 'sw' ),
		'cheie'  => rest_url( 'eg/v1/push-cheie' ),
		'abonez' => rest_url( 'eg/v1/push-abonare' ),
		'icon'   => EG_URI . '/assets/pwa/icon-192.png',
	);
	?>
<div id="eg-app" hidden><div class="eg-app-box"><div class="eg-app-cap"><img alt="" data-no-lazy="1" class="skip-lazy no-lazyload" loading="eager" width="52" height="52" src="<?php echo esc_url( $cfg['icon'] ); ?>"><div class="eg-app-txt"><strong></strong><span></span></div><button type="button" class="eg-app-nu" aria-label="Închide">×</button></div><div class="eg-app-extra"></div><button type="button" class="eg-app-da"></button></div><div class="eg-app-sageata" hidden aria-hidden="true">⬇</div></div>
<style>
#eg-app{position:fixed;left:10px;right:10px;bottom:12px;z-index:99998;display:flex;flex-direction:column;align-items:center;pointer-events:none}
#eg-app[hidden]{display:none}
.eg-app-box{pointer-events:auto;max-width:460px;width:100%;background:linear-gradient(160deg,#1546A8,#0B2A6F);color:#fff;border:1px solid rgba(255,255,255,.16);border-radius:18px;padding:14px 14px 14px;box-shadow:0 18px 50px rgba(0,0,0,.45);font-family:Inter,system-ui,sans-serif;animation:egApp .35s ease-out}
@keyframes egApp{from{transform:translateY(30px);opacity:0}to{transform:none;opacity:1}}
.eg-app-cap{display:flex;align-items:center;gap:12px}
.eg-app-cap img{width:52px;height:52px;border-radius:12px;flex:none;box-shadow:0 4px 12px rgba(0,0,0,.35)}
.eg-app-txt{flex:1;min-width:0;line-height:1.3}.eg-app-txt strong{display:block;font-size:17px}.eg-app-txt span{font-size:13.5px;opacity:.8}
.eg-app-nu{flex:none;align-self:flex-start;border:0;background:rgba(255,255,255,.1);color:#fff;font-size:20px;line-height:1;width:30px;height:30px;border-radius:50%;cursor:pointer}
.eg-app-extra{margin-top:12px}.eg-app-extra:empty{display:none}
.eg-app-pasi{list-style:none;margin:0;padding:0;display:grid;gap:8px}
.eg-app-pasi li{display:flex;align-items:center;gap:11px;background:rgba(255,255,255,.07);border-radius:11px;padding:9px 11px;font-size:14.5px;line-height:1.3}
.eg-app-pasi i{flex:none;width:34px;height:34px;border-radius:9px;background:#fff;color:#1546A8;display:flex;align-items:center;justify-content:center;font-style:normal;font-size:18px}
.eg-app-pasi i svg{width:20px;height:20px}
.eg-app-pasi b{font-weight:700}
.eg-app-pasi .nr{flex:none;width:20px;height:20px;border-radius:50%;background:#F5C518;font-size:12px;font-weight:800;display:flex;align-items:center;justify-content:center}
.eg-app-da{display:block;width:100%;margin-top:12px;border:0;border-radius:11px;background:#F5C518;color:#0B2A6F;font-weight:800;padding:13px 14px;font-size:16px;cursor:pointer}
.eg-app-da[hidden]{display:none}
.eg-app-da.eg-app-gata{background:rgba(255,255,255,.12);font-weight:600;font-size:14px;padding:10px}
.eg-app-sageata{pointer-events:none;margin-top:6px;font-size:30px;color:#F5C518;text-shadow:0 2px 8px rgba(0,0,0,.5);animation:egSag 1s ease-in-out infinite}
.eg-app-sageata[hidden]{display:none}
@keyframes egSag{50%{transform:translateY(7px)}}
</style>
<script>
(function(){
var C=<?php echo wp_json_encode( $cfg ); ?>;
if(!('serviceWorker' in navigator))return;
var reg=navigator.serviceWorker.register(C.sw,{scope:'/'});
function ls(k,v){try{if(v===undefined)return localStorage.getItem(k);localStorage.setItem(k,v);}catch(e){return null;}}
function recent(k,zile){var t=+ls(k)||0;return Date.now()-t<zile*864e5;}
var box=document.getElementById('eg-app'),tt=box.querySelector('strong'),tx=box.querySelector('.eg-app-txt span'),ex=box.querySelector('.eg-app-extra'),da=box.querySelector('.eg-app-da'),nu=box.querySelector('.eg-app-nu'),sag=box.querySelector('.eg-app-sageata'),cheieNu='eg_app_nu';
var SHARE='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7.5 7.5 4.5-4.5 4.5 4.5"/><path d="M6 11H5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-8a1 1 0 0 0-1-1h-1"/></svg>';
var PLUS='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><rect x="3.5" y="3.5" width="17" height="17" rx="4"/><path d="M12 8v8M8 12h8"/></svg>';
function lista(rows){return '<ul class="eg-app-pasi">'+rows.map(function(r){return '<li>'+(r[2]?'<span class="nr">'+r[2]+'</span>':'')+'<i>'+r[0]+'</i><span>'+r[1]+'</span></li>';}).join('')+'</ul>';}
var AVANTAJE=lista([['🔔','<b>Notificări</b> la cronicile meciurilor și transmisiile live'],['⚡','Se deschide <b>instant</b>, pe tot ecranul'],['📶','Rulează și <b>fără internet</b>']]);
function arata(t,x,html,b,fn,k,cuSageata){if(recent(k||cheieNu,14))return;tt.textContent=t;tx.textContent=x;ex.innerHTML=html||'';da.hidden=!b;da.textContent=b||'';da.onclick=fn;sag.hidden=!cuSageata;nu.onclick=function(){ls(k||cheieNu,Date.now());box.hidden=true;};box.hidden=false;}
var standalone=window.matchMedia('(display-mode: standalone)').matches||navigator.standalone===true;
var ios=/iphone|ipad|ipod/i.test(navigator.userAgent)&&!window.MSStream;
function b64(s){s=s.replace(/-/g,'+').replace(/_/g,'/');var p=atob(s+'==='.slice((s.length+3)%4)),a=new Uint8Array(p.length);for(var i=0;i<p.length;i++)a[i]=p.charCodeAt(i);return a;}
function aboneaza(){
 Notification.requestPermission().then(function(p){if(p!=='granted'){box.hidden=true;return;}
  return reg.then(function(r){return fetch(C.cheie).then(function(x){return x.json();}).then(function(j){
   return r.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:b64(j.cheie)});}).then(function(s){
   return fetch(C.abonez,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(s)});}).then(function(){
   ls('eg_app_abonat',Date.now());tt.textContent='Gata! Notificările sunt pornite ⚽';tx.textContent='Te anunțăm la cronicile meciurilor și la transmisiile live.';ex.innerHTML='';da.hidden=true;setTimeout(function(){box.hidden=true;},4000);});});
 }).catch(function(){box.hidden=true;});
}
function oferaNotificari(){
 if(!('PushManager' in window)||!('Notification' in window)||Notification.permission!=='default'||ls('eg_app_abonat'))return;
 setTimeout(function(){arata('Pornește notificările','Află primul ce se întâmplă:',lista([['📰','<b>Cronicile</b> meciurilor importante, imediat după final'],['🔴','<b>Transmisiile live</b>, la start'],['📖','Fără spam: știrile obișnuite nu trimit notificări']]),'🔔 Activează notificările',aboneaza,'eg_notif_nu');},800);
}
if(standalone){ls(cheieNu,Date.now());oferaNotificari();return;}
var amanat=null;
window.addEventListener('beforeinstallprompt',function(e){e.preventDefault();amanat=e;
 setTimeout(function(){arata('Instalează aplicația EuroGol','Gratuită, fără magazin de aplicații, ocupă foarte puțin loc.',AVANTAJE,'📲 Instalează aplicația',function(){
  box.hidden=true;amanat.prompt();amanat.userChoice.then(function(r){if(r.outcome!=='accepted')ls(cheieNu,Date.now());});});},800);});
window.addEventListener('appinstalled',function(){box.hidden=true;ls(cheieNu,Date.now());});
if(ios&&/safari/i.test(navigator.userAgent)&&!/crios|fxios/i.test(navigator.userAgent)){
 // Safari nu poate ști dacă aplicația e deja pe ecran (aplicația are memoria ei separată): bannerul apare de cel mult 3 ori și dispare definitiv la „Am instalat-o deja”.
 if(ls('eg_app_ios_gata')||(+ls('eg_app_ios_n')||0)>=3)return;
 setTimeout(function(){if(recent(cheieNu,14))return;ls('eg_app_ios_n',(+ls('eg_app_ios_n')||0)+1);arata('Pune EuroGol pe iPhone','Gratuit, în trei pași, direct din Safari:',lista([[SHARE,'Apasă <b>Partajează</b> în bara de jos a Safari','1'],[PLUS,'Derulează și alege <b>„Adaugă pe ecranul principal”</b>','2'],['✅','Apasă <b>Adaugă</b>, sus în dreapta. Gata!','3']]),'Am instalat-o deja',function(){ls('eg_app_ios_gata',Date.now());box.hidden=true;},null,true);da.className='eg-app-da eg-app-gata';},800);
}else if(!ios){oferaNotificari();}
})();
</script>
	<?php
}, 30 );

/* ---------- notificări push ---------- */
function eg_pwa_b64u( $s ) {
	return rtrim( strtr( base64_encode( $s ), '+/', '-_' ), '=' );
}
/** Cheile VAPID: generate o singură dată pe server, păstrate în opțiuni (nu se încarcă automat). */
function eg_pwa_chei() {
	$priv = get_option( 'eg_vapid_priv' );
	$pub  = get_option( 'eg_vapid_pub' );
	if ( $priv && $pub ) {
		return array( $priv, $pub );
	}
	$k = openssl_pkey_new( array( 'curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC ) );
	if ( ! $k ) {
		return array( '', '' );
	}
	openssl_pkey_export( $k, $priv );
	$d   = openssl_pkey_get_details( $k );
	$pub = eg_pwa_b64u( "\x04" . str_pad( $d['ec']['x'], 32, "\0", STR_PAD_LEFT ) . str_pad( $d['ec']['y'], 32, "\0", STR_PAD_LEFT ) );
	update_option( 'eg_vapid_priv', $priv, false );
	update_option( 'eg_vapid_pub', $pub, false );
	return array( $priv, $pub );
}
/** Semnătura ES256 (DER → r||s de 64 de octeți). */
function eg_pwa_jwt( $aud ) {
	list( $priv ) = eg_pwa_chei();
	$h = eg_pwa_b64u( wp_json_encode( array( 'typ' => 'JWT', 'alg' => 'ES256' ) ) );
	$p = eg_pwa_b64u( wp_json_encode( array( 'aud' => $aud, 'exp' => time() + 12 * HOUR_IN_SECONDS, 'sub' => home_url( '/' ) ) ) );
	if ( ! openssl_sign( "$h.$p", $der, $priv, OPENSSL_ALGO_SHA256 ) ) {
		return '';
	}
	$o = 3;
	$rl = ord( $der[ $o ] );
	$r  = substr( $der, $o + 1, $rl );
	$o  = $o + 1 + $rl + 1;
	$sl = ord( $der[ $o ] );
	$s  = substr( $der, $o + 1, $sl );
	$fix = function ( $x ) { return str_pad( ltrim( $x, "\0" ), 32, "\0", STR_PAD_LEFT ); };
	return "$h.$p." . eg_pwa_b64u( $fix( $r ) . $fix( $s ) );
}
function eg_pwa_tabel() {
	global $wpdb;
	return $wpdb->prefix . 'eg_push';
}
add_action( 'init', function () {
	if ( get_option( 'eg_push_tabel' ) === EG_PWA_VERSIUNE ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( 'CREATE TABLE ' . eg_pwa_tabel() . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		h char(32) NOT NULL,
		endpoint text NOT NULL,
		creat datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY h (h)
	) " . $wpdb->get_charset_collate() . ';' );
	update_option( 'eg_push_tabel', EG_PWA_VERSIUNE, false );
} );

add_action( 'rest_api_init', function () {
	register_rest_route( 'eg/v1', '/push-cheie', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function () { return array( 'cheie' => eg_pwa_chei()[1] ); },
	) );
	register_rest_route( 'eg/v1', '/push-abonare', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( $r ) {
			global $wpdb;
			$e = esc_url_raw( (string) $r->get_param( 'endpoint' ) );
			$h = wp_parse_url( $e, PHP_URL_HOST );
			// doar serviciile de push ale browserelor (Google, Mozilla, Apple, Microsoft)
			if ( ! $e || ! preg_match( '/(^|\.)(googleapis\.com|mozilla\.com|push\.apple\.com|notify\.windows\.com)$/', (string) $h ) ) {
				return new WP_Error( 'ps', 'Abonare invalidă', array( 'status' => 400 ) );
			}
			$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . eg_pwa_tabel() . ' (h, endpoint, creat) VALUES (%s, %s, %s)', md5( $e ), $e, current_time( 'mysql' ) ) );
			return array( 'ok' => true );
		},
	) );
	register_rest_route( 'eg/v1', '/notificare', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function () {
			$n = get_option( 'eg_push_ultima', array() );
			return $n ? $n : array( 'titlu' => 'EuroGol', 'text' => 'Avem noutăți pe EuroGol.', 'url' => home_url( '/' ) );
		},
	) );
	register_rest_route( 'eg/v1', '/push-stare', array(
		'methods'             => 'GET',
		'permission_callback' => function () { return current_user_can( 'manage_options' ); },
		'callback'            => function () {
			global $wpdb;
			return array( 'abonati' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . eg_pwa_tabel() ), 'ultima' => get_option( 'eg_push_ultima' ), 'jurnal' => get_option( 'eg_push_jurnal' ) );
		},
	) );
	register_rest_route( 'eg/v1', '/push-trimite', array(
		'methods'             => 'POST',
		'permission_callback' => function () { return current_user_can( 'manage_options' ); },
		'callback'            => function ( $r ) {
			$p = (int) $r->get_param( 'post' );
			if ( $p ) {
				eg_pwa_notifica_articol( $p, true );
			} else {
				eg_pwa_notifica( (string) $r->get_param( 'titlu' ), (string) $r->get_param( 'text' ), (string) $r->get_param( 'url' ) );
			}
			return array( 'trimis' => true );
		},
	) );
} );

/** Pregătește notificarea și o trimite tuturor abonaților în fundal (WP-Cron). */
function eg_pwa_notifica( $titlu, $text, $url, $imagine = '', $tag = 'eg' ) {
	update_option( 'eg_push_ultima', array( 'titlu' => wp_strip_all_tags( $titlu ), 'text' => wp_strip_all_tags( $text ), 'url' => esc_url_raw( $url ? $url : home_url( '/' ) ), 'imagine' => esc_url_raw( $imagine ), 'tag' => sanitize_key( $tag ), 'timp' => time() ), false );
	wp_schedule_single_event( time(), 'eg_pwa_trimite', array( time() ) );
	spawn_cron();
}
add_action( 'eg_pwa_trimite', function () {
	global $wpdb;
	$list = $wpdb->get_results( 'SELECT id, endpoint FROM ' . eg_pwa_tabel() );
	$jwt  = array();
	$pub  = eg_pwa_chei()[1];
	$ok   = 0;
	$sterse = 0;
	foreach ( $list as $a ) {
		$u   = wp_parse_url( $a->endpoint );
		$aud = $u['scheme'] . '://' . $u['host'];
		if ( ! isset( $jwt[ $aud ] ) ) {
			$jwt[ $aud ] = eg_pwa_jwt( $aud );
		}
		$res  = wp_remote_post( $a->endpoint, array( 'timeout' => 10, 'headers' => array( 'Authorization' => 'vapid t=' . $jwt[ $aud ] . ', k=' . $pub, 'TTL' => '43200', 'Urgency' => 'high', 'Content-Length' => '0' ), 'body' => '' ) );
		$cod  = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		if ( 404 === $cod || 410 === $cod ) {
			$wpdb->delete( eg_pwa_tabel(), array( 'id' => $a->id ) );
			$sterse++;
		} elseif ( $cod >= 200 && $cod < 300 ) {
			$ok++;
		}
	}
	update_option( 'eg_push_jurnal', array( 'cand' => current_time( 'mysql' ), 'abonati' => count( $list ), 'trimise' => $ok, 'sterse' => $sterse ), false );
} );

/**
 * Ce primește notificare: cronicile meciurilor (articolele cu câmpul „meci”, _eg_cronica) și transmisiile live (eticheta „Live”).
 * Știrile obișnuite și poveștile nu trimit notificări.
 */
function eg_pwa_notifica_articol( $id, $forteaza = false ) {
	$p = get_post( $id );
	if ( ! $p || 'publish' !== $p->post_status || 'post' !== $p->post_type ) {
		return;
	}
	if ( ! $forteaza && get_post_meta( $id, '_eg_push', true ) ) {
		return;
	}
	$titlu = get_the_title( $p );
	$cap   = '';
	if ( get_post_meta( $id, '_eg_cronica', true ) ) {
		$cap = 'Cronica meciului';
	} elseif ( has_tag( 'live', $p ) || has_tag( 'Live', $p ) ) {
		$cap = '🔴 Live';
	}
	if ( ! $cap && ! $forteaza ) {
		return;
	}
	update_post_meta( $id, '_eg_push', time() );
	$img = get_the_post_thumbnail_url( $p, 'medium_large' );
	eg_pwa_notifica( $cap ? $cap : 'EuroGol', $titlu, get_permalink( $p ), $img ? $img : '', 'art' . $id );
}
add_action( 'transition_post_status', function ( $nou, $vechi, $post ) {
	if ( 'publish' !== $nou || 'publish' === $vechi || 'post' !== $post->post_type ) {
		return;
	}
	if ( strtotime( $post->post_date_gmt . ' UTC' ) < time() - DAY_IN_SECONDS ) {
		return; // nu anunțăm articolele vechi (importuri din arhivă, re-datări)
	}
	// după ce s-au salvat categoriile și coperta
	wp_schedule_single_event( time() + 90, 'eg_pwa_articol', array( $post->ID ) );
}, 30, 3 );
add_action( 'eg_pwa_articol', 'eg_pwa_notifica_articol' );
