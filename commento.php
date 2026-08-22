<?php
/**
 * commento.php
 * Interfaccia Live Commentary con Toolbar Eventi Avanzata.
 */

define('FP_DISABLE_DISPATCH', true);

$rootConfigPath = __DIR__ . '/config.php';
$rootFunctionPath = __DIR__ . '/function.php';
$socialFunctionPath = __DIR__ . '/social/function.php';
$commonFunctionsPath = __DIR__ . '/nuovo/common_functions.php';
$authPath = __DIR__ . '/auth.php';

if (file_exists($rootConfigPath)) require_once $rootConfigPath;
if (file_exists($rootFunctionPath)) require_once $rootFunctionPath;
elseif (file_exists($socialFunctionPath)) require_once $socialFunctionPath;
if (file_exists($commonFunctionsPath)) require_once $commonFunctionsPath;
if (!function_exists('checkAuth') && file_exists($authPath)) require_once $authPath;
checkAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'save-commentary') {
    header('Content-Type: application/json; charset=utf-8');
    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody, true);
    if ($payload === null && json_last_error() !== JSON_ERROR_NONE) {
        $parsedBody = [];
        parse_str($rawBody, $parsedBody);
        $payload = $parsedBody ?: null;
    }
    if ($payload === null && !empty($_POST)) $payload = $_POST;
    $commentary = is_array($payload) ? ($payload['commentary'] ?? $payload) : [];
    if (is_string($commentary)) $commentary = json_decode($commentary, true) ?: [];
    if (!is_array($commentary)) $commentary = [];

    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Impossibile creare la cartella uploads.']);
        exit;
    }
    $filePath = $uploadDir . DIRECTORY_SEPARATOR . 'commentary.json';
    $encoded = json_encode($commentary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($encoded === false || file_put_contents($filePath, $encoded) === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Errore nel salvataggio del file.']);
        exit;
    }
    echo json_encode(['success' => true, 'file' => 'uploads/commentary.json']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
<title>Live Commentary - Formula Paddock</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
body{font-family:Inter,sans-serif}.event-btn{transition:all .3s;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;border:2px solid rgba(255,255,255,.05);position:relative;overflow:hidden;padding:12px 8px}.event-btn:hover{transform:translateY(-3px);background:rgba(255,255,255,.08);border-color:rgba(255,255,255,.2)}.events-container-scroll{display:flex;flex-wrap:nowrap;gap:.5rem;padding:.5rem;width:100%}.events-container-scroll>button{flex-shrink:0;min-width:fit-content}@media(max-width:768px){.events-section-mobile{max-height:140px;overflow-x:auto;overflow-y:hidden}.events-container-scroll{padding:12px 8px}.feed-content-mobile{overflow-y:auto;padding:12px}}
</style>
</head>
<body class="bg-slate-900 min-h-screen">
<div id="commentary-modal" class="fixed inset-0 bg-black/95 backdrop-blur-xl z-[100] flex items-center justify-center p-4"><div class="w-full max-w-7xl bg-slate-900 border border-white/20 rounded-[2rem] shadow-2xl overflow-hidden flex flex-col h-[90vh]">
<header class="bg-rose-600 p-4 flex justify-between items-center"><div class="flex items-center gap-3"><i class="fa-solid fa-microphone-lines text-white"></i><h2 class="font-bold text-white text-lg">Live Commentary</h2></div><div class="flex items-center gap-4"><div class="flex items-center gap-2 bg-slate-800 p-2 rounded-lg"><button id="timer-toggle-btn" onclick="window.toggleCommTimer()" class="w-8 h-8 rounded-lg bg-white/10 text-white" title="Timer manuale di riserva"><i id="timer-play-icon" class="fa-solid fa-play"></i></button><button id="timer-reset-btn" onclick="window.resetCommTimer()" class="w-8 h-8 rounded-lg bg-white/10 text-white" title="Azzera timer manuale"><i class="fa-solid fa-rotate-right"></i></button><div id="comm-timer-display" class="text-lg font-mono font-bold text-white px-2" title="Timer sincronizzato con Live Timing quando disponibile">00:00:00</div><div id="live-lap-display" class="text-xs font-bold text-white bg-black/20 rounded-lg px-2 py-1">Giro --</div></div><a href="index.php" class="w-8 h-8 bg-black/30 text-white rounded-lg flex items-center justify-center"><i class="fa-solid fa-xmark"></i></a></div></header>
<div class="flex-1 flex flex-col overflow-hidden bg-slate-950">
<div class="bg-slate-900/70 border-b border-white/10 p-6 events-section-mobile"><div class="events-container-scroll">
<button onclick="window.openEventModal('bandiera-green')" class="event-btn rounded-lg text-emerald-400 bg-emerald-500/15"><i class="fa-solid fa-flag"></i><span>Verde</span></button><button onclick="window.openEventModal('bandiera-rossa')" class="event-btn rounded-lg text-red-400 bg-red-500/15"><i class="fa-solid fa-flag"></i><span>Rossa</span></button><button onclick="window.openEventModal('bandiera-gialla')" class="event-btn rounded-lg text-yellow-400 bg-yellow-500/15"><i class="fa-solid fa-flag"></i><span>Gialla</span></button><button onclick="window.openEventModal('safety-car')" class="event-btn rounded-lg text-orange-400 bg-orange-500/15"><i class="fa-solid fa-car"></i><span>Safety Car</span></button><button onclick="window.openEventModal('vsc')" class="event-btn rounded-lg text-orange-300 bg-orange-500/10"><i class="fa-solid fa-stopwatch"></i><span>VSC</span></button><button onclick="window.openEventModal('giro-veloce-assoluto')" class="event-btn rounded-lg text-purple-400 bg-purple-500/15"><i class="fa-solid fa-gauge-high"></i><span>Veloce</span></button><button onclick="window.openEventModal('giro-veloce-personale')" class="event-btn rounded-lg text-emerald-300 bg-emerald-500/10"><i class="fa-solid fa-gauge"></i><span>Record</span></button><button onclick="window.openEventModal('giro-normale')" class="event-btn rounded-lg text-slate-400 bg-slate-500/10"><i class="fa-solid fa-circle-notch"></i><span>Giro</span></button><button onclick="window.openEventModal('pit-stop')" class="event-btn rounded-lg text-blue-400 bg-blue-500/15"><i class="fa-solid fa-wrench"></i><span>Pit Stop</span></button><button onclick="window.openEventModal('sorpasso')" class="event-btn rounded-lg text-indigo-400 bg-indigo-500/15"><i class="fa-solid fa-right-left"></i><span>Sorpasso</span></button><button onclick="window.openEventModal('note')" class="event-btn rounded-lg text-slate-300 bg-slate-500/15"><i class="fa-solid fa-note-sticky"></i><span>Nota</span></button></div></div>
<div class="flex-1 flex flex-col overflow-hidden"><div id="commentary-feed" class="flex-1 overflow-y-auto p-4 space-y-4"><div class="text-center text-slate-500 mt-8"><p class="text-sm font-bold">Pronto per il Commentary</p></div></div><div class="p-4 bg-slate-900/80 border-t border-white/10 flex justify-between"><button onclick="window.confirmReset()" class="text-xs font-bold text-slate-400">Reset</button><button onclick="window.importCommentary()" class="bg-white text-slate-900 px-4 py-2 rounded-lg font-bold text-xs">Importa</button></div></div></div></div></div>
<div id="event-input-modal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-[120] hidden flex items-center justify-center p-4"><div class="bg-slate-800 border border-white/20 rounded-[2rem] w-full max-w-lg shadow-2xl overflow-hidden"><header class="bg-slate-700/80 p-6 flex justify-between"><h3 id="event-modal-title" class="text-white font-black uppercase italic text-sm">Dettaglio Evento</h3><button onclick="document.getElementById('event-input-modal').classList.add('hidden')" class="text-slate-400"><i class="fa-solid fa-xmark"></i></button></header><div class="p-6"><div id="event-form-fields"></div><div class="mt-8 flex gap-4"><button onclick="document.getElementById('event-input-modal').classList.add('hidden')" class="flex-1 py-4 rounded-xl bg-slate-700 text-white font-bold">Annulla</button><button onclick="window.saveEvent()" class="flex-1 py-4 rounded-xl bg-rose-600 text-white font-black">Salva Evento</button></div></div></div></div>
<script>
let commentaryData=[];let currentEventType=null;let driversData=[];let currentLiveLap=null;
let liveTimerSynced=false;let lastLiveTimerAt=0;let lastLiveErrorLogAt=0;
const LIVE_DATA_URL='/live-data.php';
const SESSION_RESULTS_URL='/seo/api/session-results-gdrive.php';

async function fetchJson(url){
    const response=await fetch(`${url}${url.includes('?')?'&':'?'}t=${Date.now()}`,{cache:'no-store',headers:{'Accept':'application/json'}});
    if(!response.ok)throw new Error(`HTTP ${response.status}`);
    const text=await response.text();
    try{return JSON.parse(text);}catch(error){throw new Error(`Risposta non JSON da ${url}`);}
}

function firstArray(data){
    const candidates=[data?.drivers,data?.classification,data?.standings,data?.rows,data?.data?.drivers,data?.data?.classification,data?.data?.standings,data?.data?.rows,data?.live?.drivers,data?.live?.classification];
    return candidates.find(Array.isArray)||[];
}

function normalizeDriver(raw,index){
    if(!raw||typeof raw!=='object')return null;
    const number=raw.driver_number??raw.driverNumber??raw.number??raw.racing_number??raw.racingNumber??raw.RacingNumber??raw.car_number??raw.carNumber??'';
    const name=raw.full_name??raw.fullName??raw.driver_name??raw.driverName??raw.broadcast_name??raw.broadcastName??raw.BroadcastName??raw.name??raw.pilota??'';
    const cleanName=String(name||'').replace(/\s+/g,' ').trim();
    if(!cleanName)return null;
    return {
        driver_number:String(number||cleanName||index+1),
        broadcast_name:cleanName,
        full_name:cleanName,
        first_name:String(raw.first_name??raw.firstName??raw.FirstName??''),
        last_name:String(raw.last_name??raw.lastName??raw.LastName??''),
        team_name:String(raw.team_name??raw.teamName??raw.TeamName??raw.team??''),
        position:raw.position??raw.Position??index+1,
        raw
    };
}

function updateDriversFromData(data){
    const source=firstArray(data);
    const normalized=source.map(normalizeDriver).filter(Boolean);
    if(normalized.length){
        const seen=new Set();
        driversData=normalized.filter(driver=>{
            const key=`${driver.driver_number}|${driver.full_name}`.toLowerCase();
            if(seen.has(key))return false;
            seen.add(key);return true;
        });
    }
    return driversData.length;
}

async function loadDriversFallback(){
    try{
        const data=await fetchJson(SESSION_RESULTS_URL);
        if(data?.success!==false)updateDriversFromData(data);
    }catch(error){
        console.error('Errore fallback piloti:',error);
    }
}

function numericLap(raw){
    const value=raw?.numberOfLaps??raw?.NumberOfLaps??raw?.laps??raw?.lapCount??raw?.lap_count??raw?.total_laps??raw?.currentLap??raw?.current_lap??raw?.lap;
    const number=Number(value);
    return Number.isFinite(number)&&number>=0?number:null;
}

function updateCurrentLapFromData(data){
    const laps=firstArray(data).map(numericLap).filter(value=>value!==null);
    if(laps.length)currentLiveLap=Math.max(...laps);
    const display=document.getElementById('live-lap-display');
    if(display)display.textContent=currentLiveLap!==null?`Giro ${currentLiveLap}`:'Giro --';
}

function normalizeTimer(value){
    if(value===null||value===undefined||value==='')return null;
    if(typeof value==='number'&&Number.isFinite(value)){
        let seconds=value;
        if(seconds>86400&&seconds<=86400000)seconds=Math.round(seconds/1000);
        if(seconds<0||seconds>86400)return null;
        seconds=Math.round(seconds);
        const h=String(Math.floor(seconds/3600)).padStart(2,'0');
        const m=String(Math.floor((seconds%3600)/60)).padStart(2,'0');
        const s=String(seconds%60).padStart(2,'0');
        return `${h}:${m}:${s}`;
    }
    if(typeof value==='string'){
        const clean=value.trim();
        if(/^\d{1,3}:\d{2}:\d{2}(?:\.\d+)?$/.test(clean)||/^\d{1,3}:\d{2}$/.test(clean))return clean;
        if(/^\d+(?:\.\d+)?$/.test(clean))return normalizeTimer(Number(clean));
    }
    if(typeof value==='object'){
        for(const key of ['value','formatted','display','time','remaining','seconds']){
            const normalized=normalizeTimer(value?.[key]);
            if(normalized)return normalized;
        }
    }
    return null;
}

function extractLiveTimer(data){
    const explicit=[
        data?.timer,data?.session_timer,data?.sessionTimer,data?.session_time,data?.sessionTime,
        data?.remaining_time,data?.remainingTime,data?.time_remaining,data?.timeRemaining,data?.time_left,data?.timeLeft,
        data?.session?.timer,data?.session?.session_timer,data?.session?.sessionTimer,data?.session?.remaining,data?.session?.remainingTime,data?.session?.timeRemaining,data?.session?.time_left,data?.session?.timeLeft,
        data?.clock?.time,data?.clock?.remaining,data?.clock?.value,data?.data?.timer,data?.data?.sessionTimer,data?.data?.remainingTime,data?.live?.timer,data?.live?.remainingTime
    ];
    for(const candidate of explicit){const normalized=normalizeTimer(candidate);if(normalized)return normalized;}

    const wanted=/^(timer|session[_-]?timer|session[_-]?time|remaining(?:[_-]?time)?|time[_-]?remaining|time[_-]?left|clock)$/i;
    const queue=[data];const visited=new Set();let inspected=0;
    while(queue.length&&inspected<600){
        const item=queue.shift();inspected++;
        if(!item||typeof item!=='object'||visited.has(item))continue;
        visited.add(item);
        for(const [key,value] of Object.entries(item)){
            if(wanted.test(key)){
                const normalized=normalizeTimer(value);
                if(normalized)return normalized;
            }
            if(value&&typeof value==='object')queue.push(value);
        }
    }
    return null;
}

function applyLiveTimer(timer){
    if(!timer)return false;
    liveTimerSynced=true;lastLiveTimerAt=Date.now();
    if(commTimerInterval){clearInterval(commTimerInterval);commTimerInterval=null;}
    const display=document.getElementById('comm-timer-display');
    if(display){display.textContent=timer;display.title='Timer sincronizzato con il Live Timing';}
    const toggle=document.getElementById('timer-toggle-btn');
    const reset=document.getElementById('timer-reset-btn');
    const icon=document.getElementById('timer-play-icon');
    if(toggle){toggle.disabled=true;toggle.classList.add('opacity-50','cursor-not-allowed');toggle.title='Sincronizzato con Live Timing';}
    if(reset){reset.disabled=true;reset.classList.add('opacity-50','cursor-not-allowed');reset.title='Sincronizzato con Live Timing';}
    if(icon)icon.className='fa-solid fa-link';
    return true;
}

function releaseLiveTimerIfStale(){
    if(!liveTimerSynced||Date.now()-lastLiveTimerAt<10000)return;
    liveTimerSynced=false;
    const toggle=document.getElementById('timer-toggle-btn');
    const reset=document.getElementById('timer-reset-btn');
    const icon=document.getElementById('timer-play-icon');
    if(toggle){toggle.disabled=false;toggle.classList.remove('opacity-50','cursor-not-allowed');toggle.title='Timer manuale di riserva';}
    if(reset){reset.disabled=false;reset.classList.remove('opacity-50','cursor-not-allowed');reset.title='Azzera timer manuale';}
    if(icon)icon.className=commTimerInterval?'fa-solid fa-pause':'fa-solid fa-play';
}

async function syncLiveState({needDrivers=false}={}){
    try{
        const data=await fetchJson(LIVE_DATA_URL);
        const count=updateDriversFromData(data);
        updateCurrentLapFromData(data);
        const timer=extractLiveTimer(data);
        if(timer)applyLiveTimer(timer);else releaseLiveTimerIfStale();
        if(needDrivers&&!count)await loadDriversFallback();
        return data;
    }catch(error){
        releaseLiveTimerIfStale();
        if(needDrivers&&!driversData.length)await loadDriversFallback();
        if(Date.now()-lastLiveErrorLogAt>30000){console.error('Errore sincronizzazione Live Timing:',error);lastLiveErrorLogAt=Date.now();}
        return null;
    }
}

async function loadDrivers(){await syncLiveState({needDrivers:true});}
async function loadCurrentLap(){await syncLiveState();}

function escapeHtml(value){return String(value??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}
function driverLabel(driver){return driver.full_name||driver.broadcast_name||`${driver.first_name||''} ${driver.last_name||''}`.trim()||`Pilota #${driver.driver_number??''}`;}
function driverValue(driver){return driverLabel(driver);}
function generateDriverSelect(elementId,label){const uniqueDrivers=Array.from(new Map(driversData.filter(d=>d&&typeof d==='object').map(d=>[`${String(d.driver_number)}|${driverLabel(d)}`,d])).values());const options=uniqueDrivers.map(driver=>`<option value="${escapeHtml(driverValue(driver))}">${escapeHtml(driverLabel(driver))}</option>`).join('');return `<div class="mb-4"><label class="block text-white text-sm font-bold mb-2">${escapeHtml(label)}</label><select id="${elementId}" class="w-full p-3 rounded-xl bg-slate-700 text-white border border-slate-600"><option value="">Seleziona pilota</option>${options}</select></div>`;}

const eventTitles={'bandiera-green':'Bandiera Verde','bandiera-rossa':'Bandiera Rossa','bandiera-gialla':'Bandiera Gialla','safety-car':'Safety Car','vsc':'Virtual Safety Car','giro-veloce-assoluto':'Giro Più Veloce','giro-veloce-personale':'Record Personale','giro-normale':'Giro Standard','pit-stop':'Pit Stop','sorpasso':'Sorpasso','note':'Nota'};
const eventIcons={'bandiera-green':'fa-flag text-emerald-400','bandiera-rossa':'fa-flag text-red-400','bandiera-gialla':'fa-flag text-yellow-400','safety-car':'fa-car text-orange-400','vsc':'fa-stopwatch text-orange-300','giro-veloce-assoluto':'fa-gauge-high text-purple-400','giro-veloce-personale':'fa-gauge text-emerald-300','giro-normale':'fa-circle-notch text-slate-400','pit-stop':'fa-wrench text-blue-400','sorpasso':'fa-right-left text-indigo-400','note':'fa-note-sticky text-slate-300'};

window.openEventModal=async function(eventType){await syncLiveState({needDrivers:true});currentEventType=eventType;document.getElementById('event-modal-title').textContent=eventTitles[eventType]||'Evento';let fields='';if(eventType!=='sorpasso')fields+=generateDriverSelect('pilota','Pilota');fields+=`<div class="mb-4"><label class="block text-white text-sm font-bold mb-2">Minuto/Giro</label><input type="text" id="tempo" class="w-full p-3 rounded-xl bg-slate-700 text-white border border-slate-600" placeholder="es. 1:23.456 o Giro 15"></div>`;if(['giro-veloce-assoluto','giro-veloce-personale','giro-normale','pit-stop'].includes(eventType))fields+=`<div class="mb-4"><label class="block text-white text-sm font-bold mb-2">Pneumatico</label><select id="pneumatico" class="w-full p-3 rounded-xl bg-slate-700 text-white border border-slate-600"><option value="">Seleziona mescola</option><option value="soft">Soft</option><option value="medium">Medium</option><option value="hard">Hard</option><option value="inter">Intermedie</option><option value="wet">Bagnato</option></select></div>`;if(eventType==='sorpasso'){fields+=generateDriverSelect('pilota-sorpassa','Pilota che Sorpassa');fields+=generateDriverSelect('pilota-sorpassato','Pilota Sorpassato');}fields+=`<div class="mb-4"><label class="block text-white text-sm font-bold mb-2">Commento</label><textarea id="commento" rows="3" class="w-full p-3 rounded-xl bg-slate-700 text-white border border-slate-600" placeholder="Descrivi l'evento..."></textarea></div>`;document.getElementById('event-form-fields').innerHTML=fields;document.getElementById('event-input-modal').classList.remove('hidden');};

window.saveEvent=function(){if(!currentEventType)return;const eventData={tipo:currentEventType,timestamp:new Date().toISOString(),tempo_gara:document.getElementById('comm-timer-display').textContent,giro_live:currentLiveLap};if(currentEventType!=='sorpasso')eventData.pilota=document.getElementById('pilota')?.value||'';eventData.minuto_giro=document.getElementById('tempo')?.value||'';eventData.commento=document.getElementById('commento')?.value||'';if(['giro-veloce-assoluto','giro-veloce-personale','giro-normale','pit-stop'].includes(currentEventType))eventData.pneumatico=document.getElementById('pneumatico')?.value||'';if(currentEventType==='sorpasso'){eventData.pilota_sorpassa=document.getElementById('pilota-sorpassa')?.value||'';eventData.pilota_sorpassato=document.getElementById('pilota-sorpassato')?.value||'';}commentaryData.push(eventData);localStorage.setItem('commentaryData',JSON.stringify(commentaryData));persistCommentary();updateCommentaryFeed();document.getElementById('event-input-modal').classList.add('hidden');currentEventType=null;};

function updateCommentaryFeed(){const feed=document.getElementById('commentary-feed');if(!commentaryData.length){feed.innerHTML='<div class="text-center text-slate-500 mt-8"><p class="text-sm font-bold">Pronto per il Commentary</p></div>';return;}feed.innerHTML=commentaryData.map((event,index)=>`<div class="bg-slate-900 border border-white/10 rounded-xl p-4"><div class="flex justify-between"><span class="font-bold text-white">${escapeHtml(eventTitles[event.tipo]||event.tipo)}</span><button onclick="window.deleteEvent(${index})" class="text-slate-500">×</button></div><div class="text-sm text-slate-300 mt-2">${escapeHtml(event.pilota||event.pilota_sorpassa||'')}${event.pilota_sorpassato?' → '+escapeHtml(event.pilota_sorpassato):''}</div><div class="text-xs text-slate-500 mt-1">${event.giro_live!==null&&event.giro_live!==undefined?'Giro '+escapeHtml(event.giro_live)+' · ':''}${escapeHtml(event.minuto_giro||'')} ${escapeHtml(event.commento||'')}</div></div>`).join('');}

window.deleteEvent=function(index){commentaryData.splice(index,1);localStorage.setItem('commentaryData',JSON.stringify(commentaryData));persistCommentary();updateCommentaryFeed();};
function persistCommentary(){fetch('?action=save-commentary',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({commentary:commentaryData})}).catch(error=>console.error('Errore salvataggio commentary:',error));}
window.importCommentary=function(){const input=document.createElement('input');input.type='file';input.accept='.json,application/json';input.onchange=()=>{const file=input.files?.[0];if(!file)return;const reader=new FileReader();reader.onload=()=>{try{const data=JSON.parse(reader.result);commentaryData=Array.isArray(data)?data:(Array.isArray(data?.commentary)?data.commentary:[]);localStorage.setItem('commentaryData',JSON.stringify(commentaryData));updateCommentaryFeed();}catch(error){console.error('Importazione commentary non valida:',error);}};reader.readAsText(file);};input.click();};
window.confirmReset=function(){if(confirm('Azzerare il commentary?')){commentaryData=[];localStorage.removeItem('commentaryData');persistCommentary();updateCommentaryFeed();}};
let commTimerSeconds=0;let commTimerInterval=null;
window.toggleCommTimer=function(){if(liveTimerSynced)return;if(commTimerInterval){clearInterval(commTimerInterval);commTimerInterval=null;document.getElementById('timer-play-icon').className='fa-solid fa-play';}else{commTimerInterval=setInterval(()=>{commTimerSeconds++;const h=String(Math.floor(commTimerSeconds/3600)).padStart(2,'0');const m=String(Math.floor((commTimerSeconds%3600)/60)).padStart(2,'0');const s=String(commTimerSeconds%60).padStart(2,'0');document.getElementById('comm-timer-display').textContent=`${h}:${m}:${s}`;},1000);document.getElementById('timer-play-icon').className='fa-solid fa-pause';}};
window.resetCommTimer=function(){if(liveTimerSynced)return;if(commTimerInterval){clearInterval(commTimerInterval);commTimerInterval=null;}commTimerSeconds=0;document.getElementById('comm-timer-display').textContent='00:00:00';document.getElementById('timer-play-icon').className='fa-solid fa-play';};
try{commentaryData=JSON.parse(localStorage.getItem('commentaryData')||'[]');}catch(error){commentaryData=[];}
updateCommentaryFeed();
syncLiveState({needDrivers:true});
setInterval(()=>syncLiveState(),2000);
</script>
</body>
</html>
