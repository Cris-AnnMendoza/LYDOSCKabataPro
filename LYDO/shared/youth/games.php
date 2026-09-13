<?php
require_once __DIR__ . '/../config.php';
if (empty($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }
$pdo    = db();
$userId = (int)$_SESSION['user_id'];
$uStmt  = $pdo->prepare('SELECT * FROM youth_users WHERE id=? LIMIT 1');
$uStmt->execute([$userId]);
$user = $uStmt->fetch();
$nc   = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=FALSE');
$nc->execute([$userId]);
$notifCount = (int)$nc->fetchColumn();
$game = $_GET['game'] ?? 'hub';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Mini Games  LYDO Youth Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="youth.css"/>
<style>
.game-hub{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:20px;margin-top:8px}
.game-card{background:#fff;border-radius:16px;border:1px solid #e2e8f0;box-shadow:0 2px 8px rgba(0,0,0,.07);overflow:hidden;transition:.25s;cursor:pointer;text-decoration:none;color:#1e293b}
.game-card:hover{transform:translateY(-5px);box-shadow:0 8px 24px rgba(0,0,0,.13)}
.game-card-banner{height:110px;display:flex;align-items:center;justify-content:center;font-size:3rem}
.game-card-body{padding:16px 18px}
.game-card-title{font-size:1rem;font-weight:800;margin-bottom:4px}
.game-card-desc{font-size:.82rem;color:#475569;line-height:1.5}
.game-badge{display:inline-block;padding:3px 10px;border-radius:50px;font-size:.7rem;font-weight:700;margin-top:8px}
/* Sudoku */
.sudoku-grid{display:grid;grid-template-columns:repeat(9,1fr);gap:1px;background:#94a3b8;border:2px solid #1565c0;border-radius:4px;width:fit-content;margin:0 auto}
.sudoku-cell{width:44px;height:44px;background:#fff;display:flex;align-items:center;justify-content:center;font-size:1.1rem;font-weight:600;cursor:pointer;transition:.15s;position:relative}
.sudoku-cell input{width:100%;height:100%;border:none;outline:none;text-align:center;font-size:1.1rem;font-weight:600;font-family:inherit;background:transparent;color:#1565c0;cursor:pointer}
.sudoku-cell.given{background:#f1f5f9;color:#1e293b}
.sudoku-cell.given input{color:#1e293b;cursor:default}
.sudoku-cell.selected{background:#e3f2fd}
.sudoku-cell.error input{color:#c62828}
.sudoku-cell.box-right{border-right:2px solid #1565c0}
.sudoku-cell.box-bottom{border-bottom:2px solid #1565c0}
/* Word Search */
.ws-grid{display:grid;gap:2px;width:fit-content;margin:0 auto}
.ws-cell{width:36px;height:36px;background:#fff;border:1px solid #e2e8f0;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:.95rem;font-weight:700;cursor:pointer;user-select:none;transition:.15s}
.ws-cell:hover{background:#e3f2fd}
.ws-cell.selected{background:#1565c0;color:#fff;border-color:#1565c0}
.ws-cell.found{background:#2e7d32;color:#fff;border-color:#2e7d32}
.ws-words{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}
.ws-word{padding:5px 12px;border-radius:50px;border:1.5px solid #e2e8f0;font-size:.82rem;font-weight:600;color:#475569;transition:.2s}
.ws-word.found{background:#e8f5e9;color:#2e7d32;border-color:#a5d6a7;text-decoration:line-through}
/* Trivia */
.trivia-q{font-size:1.05rem;font-weight:700;color:#1e293b;margin-bottom:18px;line-height:1.5}
.trivia-opts{display:flex;flex-direction:column;gap:10px}
.trivia-opt{padding:12px 18px;border:1.5px solid #e2e8f0;border-radius:10px;cursor:pointer;font-size:.9rem;font-weight:500;transition:.2s;background:#fff;text-align:left;font-family:inherit}
.trivia-opt:hover{border-color:#1565c0;background:#e3f2fd;color:#1565c0}
.trivia-opt.correct{background:#e8f5e9;border-color:#2e7d32;color:#2e7d32}
.trivia-opt.wrong{background:#ffebee;border-color:#c62828;color:#c62828}
.trivia-progress{height:8px;background:#e2e8f0;border-radius:50px;overflow:hidden;margin-bottom:20px}
.trivia-bar{height:100%;background:linear-gradient(90deg,#1565c0,#43a047);border-radius:50px;transition:width .4s ease}
.score-box{text-align:center;padding:32px;background:linear-gradient(135deg,#0d3b6e,#1565c0);border-radius:16px;color:#fff}
.score-num{font-size:3rem;font-weight:900;margin:12px 0}
.btn-game{padding:10px 22px;border:none;border-radius:9px;font-family:inherit;font-size:.9rem;font-weight:700;cursor:pointer;transition:.2s;display:inline-flex;align-items:center;gap:7px}
.btn-primary-g{background:linear-gradient(135deg,#0d3b6e,#1565c0);color:#fff;box-shadow:0 4px 12px rgba(21,101,192,.3)}
.btn-primary-g:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(21,101,192,.4)}
.btn-secondary-g{background:#f1f5f9;color:#475569;border:1.5px solid #e2e8f0}
.btn-secondary-g:hover{background:#e2e8f0}
.game-header{display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap}
.game-title{font-size:1.3rem;font-weight:800;flex:1}
.timer-box{background:#1565c0;color:#fff;padding:6px 16px;border-radius:8px;font-size:.9rem;font-weight:700;font-variant-numeric:tabular-nums}
.result-banner{padding:14px 18px;border-radius:10px;font-size:.9rem;font-weight:600;margin-top:14px;display:flex;align-items:center;gap:10px}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="y-main">
<?php include 'topbar.php'; ?>
<main class="y-content">

<?php if ($game === 'hub'): ?>
<div class="y-page-header">
  <h2><i class="fas fa-gamepad" style="color:#1565c0;margin-right:8px"></i>Mini Games</h2>
  <p>Take a break and have fun! Play games to sharpen your mind.</p>
</div>
<div class="game-hub">
  <a href="?game=sudoku" class="game-card">
    <div class="game-card-banner" style="background:linear-gradient(135deg,#e3f2fd,#bbdefb)">
      <i class="fas fa-th" style="font-size:3rem;color:#1565c0"></i>
    </div>
    <div class="game-card-body">
      <div class="game-card-title">Sudoku</div>
      <div class="game-card-desc">Fill the 9x9 grid so every row, column, and 3x3 box contains digits 1-9.</div>
      <span class="game-badge" style="background:#e3f2fd;color:#1565c0">Logic</span>
    </div>
  </a>
  <a href="?game=wordsearch" class="game-card">
    <div class="game-card-banner" style="background:linear-gradient(135deg,#e8f5e9,#c8e6c9)">
      <i class="fas fa-search" style="font-size:3rem;color:#2e7d32"></i>
    </div>
    <div class="game-card-body">
      <div class="game-card-title">Word Search</div>
      <div class="game-card-desc">Find hidden Filipino youth-related words in the letter grid. Race against the clock!</div>
      <span class="game-badge" style="background:#e8f5e9;color:#2e7d32">Vocabulary</span>
    </div>
  </a>
  <a href="?game=trivia" class="game-card">
    <div class="game-card-banner" style="background:linear-gradient(135deg,#fff8e1,#ffecb3)">
      <i class="fas fa-brain" style="font-size:3rem;color:#f57f17"></i>
    </div>
    <div class="game-card-body">
      <div class="game-card-title">Youth Trivia</div>
      <div class="game-card-desc">Test your knowledge about Filipino youth, LYDO, and Sta. Cruz, Laguna!</div>
      <span class="game-badge" style="background:#fff8e1;color:#f57f17">Knowledge</span>
    </div>
  </a>
</div>

<?php elseif ($game === 'sudoku'): ?>
<div class="game-header">
  <a href="?game=hub" class="btn-game btn-secondary-g"><i class="fas fa-arrow-left"></i> Back</a>
  <div class="game-title"><i class="fas fa-th" style="margin-right:6px"></i>Sudoku</div>
  <div class="timer-box" id="sudokuTimer">00:00</div>
  <button class="btn-game btn-secondary-g" onclick="newSudoku()"><i class="fas fa-redo"></i> New Game</button>
  <button class="btn-game btn-primary-g" onclick="checkSudoku()"><i class="fas fa-check"></i> Check</button>
</div>
<div style="background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:24px;box-shadow:0 2px 8px rgba(0,0,0,.06)">
  <div class="sudoku-grid" id="sudokuGrid"></div>
  <div id="sudokuResult" style="margin-top:16px"></div>
  <div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap">
    <?php for($n=1;$n<=9;$n++): ?>
    <button class="btn-game btn-secondary-g" style="width:44px;height:44px;padding:0;font-size:1.1rem;font-weight:700" onclick="insertNum(<?=$n?>)"><?=$n?></button>
    <?php endfor; ?>
    <button class="btn-game btn-secondary-g" style="width:44px;height:44px;padding:0" onclick="insertNum(0)"><i class="fas fa-eraser"></i></button>
  </div>
</div>

<?php elseif ($game === 'wordsearch'): ?>
<div class="game-header">
  <a href="?game=hub" class="btn-game btn-secondary-g"><i class="fas fa-arrow-left"></i> Back</a>
  <div class="game-title"><i class="fas fa-search" style="margin-right:6px"></i>Word Search</div>
  <div class="timer-box" id="wsTimer">02:00</div>
  <button class="btn-game btn-secondary-g" onclick="initWordSearch()"><i class="fas fa-redo"></i> New Game</button>
</div>
<div style="background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:24px;box-shadow:0 2px 8px rgba(0,0,0,.06)">
  <div class="ws-grid" id="wsGrid"></div>
  <div class="ws-words" id="wsWords"></div>
  <div id="wsResult" style="margin-top:14px"></div>
</div>

<?php elseif ($game === 'trivia'): ?>
<div class="game-header">
  <a href="?game=hub" class="btn-game btn-secondary-g"><i class="fas fa-arrow-left"></i> Back</a>
  <div class="game-title"><i class="fas fa-brain" style="margin-right:6px"></i>Youth Trivia</div>
  <div id="triviaScore" style="background:#e8f5e9;color:#2e7d32;padding:6px 16px;border-radius:8px;font-size:.9rem;font-weight:700">Score: 0</div>
</div>
<div style="background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:28px;box-shadow:0 2px 8px rgba(0,0,0,.06);max-width:680px">
  <div class="trivia-progress"><div class="trivia-bar" id="triviaBar" style="width:0%"></div></div>
  <div id="triviaContainer"></div>
</div>
<?php endif; ?>

</main>
</div>

<script>
// -- SIDEBAR ----------------------------------------------
const yHam=document.getElementById('yHamburger'),ySb=document.getElementById('ySidebar'),yOv=document.getElementById('yOverlay'),yCl=document.getElementById('ySidebarClose');
if(yHam)yHam.addEventListener('click',function(){ySb.classList.add('open');yOv.classList.add('open');});
if(yCl)yCl.addEventListener('click',function(){ySb.classList.remove('open');yOv.classList.remove('open');});
if(yOv)yOv.addEventListener('click',function(){ySb.classList.remove('open');yOv.classList.remove('open');});

// -- SUDOKU ------------------------------------------------
const PUZZLES=[
  {puzzle:[5,3,0,0,7,0,0,0,0,6,0,0,1,9,5,0,0,0,0,9,8,0,0,0,0,6,0,8,0,0,0,6,0,0,0,3,4,0,0,8,0,3,0,0,1,7,0,0,0,2,0,0,0,6,0,6,0,0,0,0,2,8,0,0,0,0,4,1,9,0,0,5,0,0,0,0,8,0,0,7,9],
   solution:[5,3,4,6,7,8,9,1,2,6,7,2,1,9,5,3,4,8,1,9,8,3,4,2,5,6,7,8,5,9,7,6,1,4,2,3,4,2,6,8,5,3,7,9,1,7,1,3,9,2,4,8,5,6,9,6,1,5,3,7,2,8,4,2,8,7,4,1,9,6,3,5,3,4,5,2,8,6,1,7,9]},
  {puzzle:[0,0,0,2,6,0,7,0,1,6,8,0,0,7,0,0,9,0,1,9,0,0,0,4,5,0,0,8,2,0,1,0,0,0,4,0,0,0,4,6,0,2,9,0,0,0,5,0,0,0,3,0,2,8,0,0,9,3,0,0,0,7,4,0,4,0,0,5,0,0,3,6,7,0,3,0,1,8,0,0,0],
   solution:[4,3,5,2,6,9,7,8,1,6,8,2,5,7,1,4,9,3,1,9,7,8,3,4,5,6,2,8,2,6,1,9,5,3,4,7,3,7,4,6,8,2,9,1,5,9,5,1,7,4,3,6,2,8,5,1,9,3,2,6,8,7,4,2,4,8,9,5,7,1,3,6,7,6,3,4,1,8,2,5,9]}
];
let sudokuPuzzle=[],sudokuSolution=[],selectedCell=-1,sudokuTimer=null,sudokuSeconds=0;

function newSudoku(){
  const p=PUZZLES[Math.floor(Math.random()*PUZZLES.length)];
  sudokuPuzzle=[...p.puzzle];sudokuSolution=[...p.solution];
  selectedCell=-1;sudokuSeconds=0;clearInterval(sudokuTimer);
  const el=document.getElementById('sudokuTimer');
  if(el){sudokuTimer=setInterval(()=>{sudokuSeconds++;el.textContent=String(Math.floor(sudokuSeconds/60)).padStart(2,'0')+':'+String(sudokuSeconds%60).padStart(2,'0');},1000);}
  renderSudoku();
  const r=document.getElementById('sudokuResult');if(r)r.innerHTML='';
}

function renderSudoku(){
  const g=document.getElementById('sudokuGrid');if(!g)return;
  g.innerHTML='';
  sudokuPuzzle.forEach((v,i)=>{
    const cell=document.createElement('div');
    cell.className='sudoku-cell'+(v!==0?' given':'');
    const col=i%9,row=Math.floor(i/9);
    if(col===2||col===5)cell.classList.add('box-right');
    if(row===2||row===5)cell.classList.add('box-bottom');
    if(v!==0){cell.innerHTML='<input type="text" readonly value="'+v+'"/>';}
    else{
      const inp=document.createElement('input');
      inp.type='text';inp.maxLength=1;inp.dataset.idx=i;
      inp.addEventListener('focus',()=>{selectedCell=i;document.querySelectorAll('.sudoku-cell').forEach(c=>c.classList.remove('selected'));cell.classList.add('selected');});
      inp.addEventListener('input',e=>{const val=e.target.value.replace(/[^1-9]/g,'');e.target.value=val;if(val)checkCellError(i,parseInt(val));else cell.classList.remove('error');});
      cell.appendChild(inp);
    }
    g.appendChild(cell);
  });
}

function insertNum(n){
  if(selectedCell<0)return;
  const inp=document.querySelector('[data-idx="'+selectedCell+'"]');
  if(!inp)return;
  inp.value=n===0?'':n;
  if(n)checkCellError(selectedCell,n);else inp.closest('.sudoku-cell').classList.remove('error');
}

function checkCellError(idx,val){
  const cell=document.querySelector('[data-idx="'+idx+'"]')?.closest('.sudoku-cell');
  if(cell)cell.classList.toggle('error',val!==sudokuSolution[idx]);
}

function checkSudoku(){
  const inputs=document.querySelectorAll('.sudoku-cell:not(.given) input');
  let correct=0,total=inputs.length;
  inputs.forEach(inp=>{const idx=parseInt(inp.dataset.idx);const val=parseInt(inp.value)||0;if(val===sudokuSolution[idx])correct++;});
  const r=document.getElementById('sudokuResult');
  if(correct===total){clearInterval(sudokuTimer);r.innerHTML='<div class="result-banner" style="background:#e8f5e9;color:#2e7d32"><i class="fas fa-trophy"></i> Solved in '+document.getElementById('sudokuTimer').textContent+'! Excellent!</div>';}
  else r.innerHTML='<div class="result-banner" style="background:#fff8e1;color:#f57f17"><i class="fas fa-info-circle"></i> '+correct+'/'+total+' correct. Keep going!</div>';
}

if(document.getElementById('sudokuGrid'))newSudoku();

// -- WORD SEARCH -------------------------------------------
const WS_WORDS=['KABATAAN','LYDO','BARANGAY','LAGUNA','PILIPINO','LIDER','SERBISYO','TALENTO','PAGBABAGO','SAMAHAN'];
const WS_SIZE=12;
let wsGrid=[],wsSelected=[],wsFound=[],wsDragging=false,wsTimerInt=null,wsTimeLeft=120;

function initWordSearch(){
  wsGrid=Array.from({length:WS_SIZE},()=>Array(WS_SIZE).fill(''));
  wsFound=[];wsSelected=[];
  const placed=[];
  const dirs=[[0,1],[1,0],[1,1],[0,-1],[-1,0],[-1,-1],[1,-1],[-1,1]];
  const words=[...WS_WORDS].sort(()=>Math.random()-.5).slice(0,8);
  words.forEach(word=>{
    let ok=false,tries=0;
    while(!ok&&tries<200){
      tries++;
      const [dr,dc]=dirs[Math.floor(Math.random()*dirs.length)];
      const r=Math.floor(Math.random()*WS_SIZE),c=Math.floor(Math.random()*WS_SIZE);
      let fits=true;
      for(let i=0;i<word.length;i++){
        const nr=r+dr*i,nc=c+dc*i;
        if(nr<0||nr>=WS_SIZE||nc<0||nc>=WS_SIZE){fits=false;break;}
        if(wsGrid[nr][nc]!==''&&wsGrid[nr][nc]!==word[i]){fits=false;break;}
      }
      if(fits){for(let i=0;i<word.length;i++)wsGrid[r+dr*i][c+dc*i]=word[i];placed.push({word,r,c,dr,dc});ok=true;}
    }
  });
  const alpha='ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  for(let r=0;r<WS_SIZE;r++)for(let c=0;c<WS_SIZE;c++)if(!wsGrid[r][c])wsGrid[r][c]=alpha[Math.floor(Math.random()*26)];
  wsTimeLeft=120;clearInterval(wsTimerInt);
  const te=document.getElementById('wsTimer');
  if(te){wsTimerInt=setInterval(()=>{wsTimeLeft--;te.textContent=String(Math.floor(wsTimeLeft/60)).padStart(2,'0')+':'+String(wsTimeLeft%60).padStart(2,'0');if(wsTimeLeft<=0){clearInterval(wsTimerInt);document.getElementById('wsResult').innerHTML='<div class="result-banner" style="background:#ffebee;color:#c62828"><i class="fas fa-clock"></i> Time is up! Found '+wsFound.length+'/'+words.length+' words.</div>';}},1000);}
  renderWS(placed,words);
}

function renderWS(placed,words){
  const g=document.getElementById('wsGrid');if(!g)return;
  g.style.gridTemplateColumns='repeat('+WS_SIZE+',36px)';
  g.innerHTML='';
  for(let r=0;r<WS_SIZE;r++)for(let c=0;c<WS_SIZE;c++){
    const cell=document.createElement('div');
    cell.className='ws-cell';cell.textContent=wsGrid[r][c];
    cell.dataset.r=r;cell.dataset.c=c;
    cell.addEventListener('mousedown',()=>{wsDragging=true;wsSelected=[[r,c]];highlightWS();});
    cell.addEventListener('mouseover',()=>{if(wsDragging){wsSelected.push([r,c]);highlightWS();}});
    cell.addEventListener('mouseup',()=>{wsDragging=false;checkWSWord(placed,words);});
    cell.addEventListener('touchstart',e=>{e.preventDefault();wsDragging=true;wsSelected=[[r,c]];highlightWS();},{passive:false});
    cell.addEventListener('touchmove',e=>{e.preventDefault();const t=e.touches[0];const el=document.elementFromPoint(t.clientX,t.clientY);if(el&&el.classList.contains('ws-cell')){const nr=parseInt(el.dataset.r),nc=parseInt(el.dataset.c);if(!wsSelected.some(([a,b])=>a===nr&&b===nc))wsSelected.push([nr,nc]);highlightWS();}},{passive:false});
    cell.addEventListener('touchend',()=>{wsDragging=false;checkWSWord(placed,words);});
    g.appendChild(cell);
  }
  const wl=document.getElementById('wsWords');if(!wl)return;
  wl.innerHTML='';words.forEach(w=>{const s=document.createElement('span');s.className='ws-word';s.textContent=w;s.id='wsw_'+w;wl.appendChild(s);});
}

function highlightWS(){
  document.querySelectorAll('.ws-cell').forEach(c=>{if(!c.classList.contains('found'))c.classList.remove('selected');});
  wsSelected.forEach(([r,c])=>{const el=document.querySelector('[data-r="'+r+'"][data-c="'+c+'"]');if(el&&!el.classList.contains('found'))el.classList.add('selected');});
}

function checkWSWord(placed,words){
  const sel=wsSelected.map(([r,c])=>wsGrid[r][c]).join('');
  const selRev=sel.split('').reverse().join('');
  placed.forEach(({word,r,c,dr,dc})=>{
    if((sel===word||selRev===word)&&!wsFound.includes(word)){
      wsFound.push(word);
      for(let i=0;i<word.length;i++){const el=document.querySelector('[data-r="'+(r+dr*i)+'"][data-c="'+(c+dc*i)+'"]');if(el){el.classList.add('found');el.classList.remove('selected');}}
      const wEl=document.getElementById('wsw_'+word);if(wEl)wEl.classList.add('found');
      if(wsFound.length===words.length){clearInterval(wsTimerInt);document.getElementById('wsResult').innerHTML='<div class="result-banner" style="background:#e8f5e9;color:#2e7d32"><i class="fas fa-trophy"></i> All words found! Amazing!</div>';}
    }
  });
  wsSelected=[];
  document.querySelectorAll('.ws-cell:not(.found)').forEach(c=>c.classList.remove('selected'));
}

if(document.getElementById('wsGrid'))initWordSearch();

// -- TRIVIA ------------------------------------------------
const TRIVIA_ORIG=[
  {q:'What does LYDO stand for?',opts:['Local Youth Development Office','Laguna Youth Development Organization','Local Youth Driven Operations','Laguna Youth District Office'],a:'Local Youth Development Office'},
  {q:'What is the minimum age to be considered part of the youth sector in the Philippines?',opts:['12 years old','15 years old','18 years old','10 years old'],a:'15 years old'},
  {q:'What law governs the National Youth Commission in the Philippines?',opts:['RA 8044','RA 7160','RA 9163','RA 10533'],a:'RA 8044'},
  {q:'Sta. Cruz is the capital of which province?',opts:['Laguna','Batangas','Quezon','Cavite'],a:'Laguna'},
  {q:'What is the Sangguniang Kabataan (SK)?',opts:['Youth council at the barangay level','A national youth organization','A government scholarship program','A youth sports league'],a:'Youth council at the barangay level'},
  {q:'Which of these is a right of Filipino youth under the law?',opts:['Right to education','Right to vote at age 15','Right to own property at 16','Right to run for president at 18'],a:'Right to education'},
  {q:'What does "Kabataan" mean in English?',opts:['Youth','Community','Leadership','Service'],a:'Youth'},
  {q:'The LYDO system helps youth organizations with which of the following?',opts:['Registration, programs, and community engagement','Tax filing and business permits','Land titling and property records','Vehicle registration'],a:'Registration, programs, and community engagement'},
  {q:'What is the maximum age to be part of the youth sector in the Philippines?',opts:['30 years old','25 years old','35 years old','28 years old'],a:'30 years old'},
  {q:'Which value is most important for youth leaders according to LYDO?',opts:['Integrity and service','Wealth and fame','Power and authority','Competition and rivalry'],a:'Integrity and service'},
];

let TRIVIA=[]; // Will be populated with shuffled options
let triviaIdx=0,triviaScore=0,triviaAnswered=false;

// Shuffle array function
function shuffleArray(array) {
  const arr = [...array];
  for (let i = arr.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [arr[i], arr[j]] = [arr[j], arr[i]];
  }
  return arr;
}

// Prepare trivia with randomized options
function prepareTrivia() {
  TRIVIA = TRIVIA_ORIG.map(item => {
    const shuffledOpts = shuffleArray(item.opts);
    const correctIdx = shuffledOpts.indexOf(item.a);
    return {
      q: item.q,
      opts: shuffledOpts,
      a: correctIdx
    };
  });
}

function loadTrivia(){
  const c=document.getElementById('triviaContainer');if(!c)return;
  if(triviaIdx>=TRIVIA.length){
    c.innerHTML='<div class="score-box"><div style="font-size:1.1rem;opacity:.85">Quiz Complete!</div><div class="score-num">'+triviaScore+'/'+TRIVIA.length+'</div><div style="opacity:.8;margin-bottom:20px">'+(triviaScore>=8?'Excellent! You know your youth rights!':triviaScore>=5?'Good job! Keep learning!':'Keep studying about youth development!')+'</div><button class="btn-game btn-primary-g" onclick="restartTrivia()"><i class="fas fa-redo"></i> Play Again</button></div>';
    return;
  }
  const q=TRIVIA[triviaIdx];triviaAnswered=false;
  const bar=document.getElementById('triviaBar');if(bar)bar.style.width=((triviaIdx/TRIVIA.length)*100)+'%';
  const sc=document.getElementById('triviaScore');if(sc)sc.textContent='Score: '+triviaScore;
  c.innerHTML='<div style="font-size:.8rem;color:#94a3b8;margin-bottom:10px">Question '+(triviaIdx+1)+' of '+TRIVIA.length+'</div><div class="trivia-q">'+q.q+'</div><div class="trivia-opts">'+q.opts.map((o,i)=>'<button class="trivia-opt" onclick="answerTrivia('+i+')">'+String.fromCharCode(65+i)+'. '+o+'</button>').join('')+'</div>';
}

function restartTrivia() {
  triviaIdx=0;
  triviaScore=0;
  prepareTrivia(); // Shuffle options again
  loadTrivia();
}

function answerTrivia(idx){
  if(triviaAnswered)return;triviaAnswered=true;
  const q=TRIVIA[triviaIdx];
  const btns=document.querySelectorAll('.trivia-opt');
  btns.forEach((b,i)=>{b.disabled=true;if(i===q.a)b.classList.add('correct');else if(i===idx&&idx!==q.a)b.classList.add('wrong');});
  if(idx===q.a)triviaScore++;
  const sc=document.getElementById('triviaScore');if(sc)sc.textContent='Score: '+triviaScore;
  setTimeout(()=>{triviaIdx++;loadTrivia();},1200);
}

if(document.getElementById('triviaContainer')){prepareTrivia();loadTrivia();}
</script>
</body>
</html>
