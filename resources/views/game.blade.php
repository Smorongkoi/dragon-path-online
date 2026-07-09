<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dragon Path Online 0.1</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="game-shell">
        <section class="topbar">
            <div>
                <p class="eyebrow">Online MVP 0.1</p>
                <h1>Dragon Path Online</h1>
            </div>
            <div class="top-actions">
                <button id="toggle-status-button" type="button">ข้อมูล</button>
                <button id="toggle-menu-button" type="button">คำสั่ง</button>
                <button id="sound-toggle-button" type="button">เสียง: เปิด</button>
                <div class="save-pill" id="total-players-text">ผู้เล่นทั้งหมด 0</div>
                <div class="save-pill" id="online-players-text">ออนไลน์ 0</div>
                <div class="save-pill" id="save-status">Loading</div>
            </div>
        </section>

        <section class="mode-switch">
            <button id="monster-mode-button" class="selected" type="button">ตีมอนสเตอร์ เก็บเลเวล</button>
            <button id="arena-mode-button" type="button">เข้าลานประลอง</button>
        </section>

        <section class="hud left-drawer">
            <div class="hero-panel">
                <div class="avatar-badge" id="class-badge">คน</div>
                <div class="hero-copy">
                    <div class="name-row">
                        <input id="player-name" maxlength="60" value="นักผจญภัย">
                        <button id="rename-button" type="button">บันทึกชื่อ</button>
                    </div>
                    <div class="evolution-strip">
                        <strong>Class Evolution</strong>
                        <p id="evolution-note">ถึงเลเวล 10 เพื่อปลดล็อกการเปลี่ยนคลาส</p>
                        <div id="class-choices" class="button-list"></div>
                    </div>
                    <p id="class-name">Class: คนปกติ</p>
                    <div class="stat-grid">
                        <span id="level-text">LV 1</span>
                        <span id="atk-text">ATK 10</span>
                        <span id="def-text">DEF 5</span>
                        <span id="special-stats-text">ATK+0 AGI+0 VIT+0 LUK+0 INT+0</span>
                    </div>
                </div>
            </div>

            <div class="bars">
                <div class="bar-row">
                    <span>HP</span>
                    <div class="bar"><i id="hp-bar"></i></div>
                    <b id="hp-text">100/100</b>
                </div>
                <div class="bar-row">
                    <span>MP</span>
                    <div class="bar mp"><i id="mp-bar"></i></div>
                    <b id="mp-text">30/30</b>
                </div>
                <div class="bar-row">
                    <span>EXP</span>
                    <div class="bar exp"><i id="exp-bar"></i></div>
                    <b id="exp-text">0/100</b>
                </div>
            </div>
        </section>

        <section class="battle-layout" id="mode-root" data-mode="world">
            <div class="canvas-wrap">
                <div id="game-canvas"></div>
            </div>

            <aside class="command-panel">
                <div class="panel-block world-panel">
                    <h2>สำรวจ</h2>
                    <div class="dice-board">
                        <div class="dice-card encounter-dice">
                            <span>เต๋าเลเวล</span>
                            <strong id="encounter-level-dice">?</strong>
                            <em id="encounter-level-effect">-</em>
                        </div>
                        <div class="dice-card encounter-dice">
                            <span>เต๋าจำนวน</span>
                            <strong id="encounter-count-dice">?</strong>
                            <em id="encounter-count-effect">-</em>
                        </div>
                    </div>
                    <button id="roll-encounter-button" class="primary-action" type="button" onclick="window.rollEncounterAction?.()">สำรวจแผนที่</button>
                </div>

                <div class="panel-block battle-panel battle-info-panel">
                    <h2>ดันเจี้ยน</h2>
                    <div id="monster-list" class="button-list"></div>
                    <div class="stat-card">
                        <div class="stat-card-head">
                            <strong id="monster-name-text">Monster</strong>
                            <span id="monster-stats-text">ATK 0 / DEF 0</span>
                        </div>
                        <div class="bar-row compact">
                            <span>HP</span>
                            <div class="bar monster"><i id="monster-hp-bar"></i></div>
                            <b id="monster-hp-text">0/0</b>
                        </div>
                    </div>
                </div>
                <div class="panel-block battle-panel">
                    <h2>Skill</h2>
                    <div id="skill-list" class="button-list"></div>
                    <div class="dice-board">
                        <div class="dice-card player-dice">
                            <span>เต๋าผู้เล่น</span>
                            <strong id="player-dice-face">?</strong>
                            <em id="player-dice-effect">-</em>
                        </div>
                        <div class="dice-card monster-dice">
                            <span>เต๋ามอนสเตอร์</span>
                            <strong id="monster-dice-face">?</strong>
                            <em id="monster-dice-effect">-</em>
                        </div>
                    </div>
                    <div class="damage-card">
                        <span id="dice-result-text">Roll: -</span>
                        <span id="hit-effect-text">Effect: -</span>
                        <span id="monster-roll-text">Monster Roll: -</span>
                        <span id="monster-last-damage-text">Monster DMG: -</span>
                        <span id="damage-estimate-text">DMG estimate: 0</span>
                        <strong id="last-damage-text">Last DMG: -</strong>
                    </div>
                </div>
                <button id="fight-button" class="primary-action battle-panel" type="button" disabled onclick="window.fightAction?.()">โจมตี</button>
                <div class="panel-block pvp-panel">
                    <h2>ลานประลอง</h2>
                    <div id="arena-player-list" class="button-list"></div>
                    <div class="stat-card">
                        <div class="stat-card-head">
                            <strong id="pvp-opponent-name">เลือกคู่ต่อสู้</strong>
                            <span id="pvp-opponent-stats">ผู้เล่นทุกคนบนโลกสู้กันได้</span>
                        </div>
                        <div class="bar-row compact">
                            <span>HP</span>
                            <div class="bar monster"><i id="pvp-opponent-hp-bar"></i></div>
                            <b id="pvp-opponent-hp-text">0/0</b>
                        </div>
                    </div>
                </div>
                <button id="pvp-fight-button" class="primary-action pvp-panel" type="button" disabled>โจมตีผู้เล่น</button>
            </aside>
        </section>

        <section class="lower-layout side-log left-log">
            <div class="log-panel">
                <h2>Battle Log</h2>
                <div id="battle-log"></div>
            </div>
            <div class="world-panel-card">
                <h2>กระดานโลก PVP</h2>
                <div id="leaderboard-list" class="rank-list"></div>
            </div>
            <div class="world-panel-card chat-card">
                <h2>แชทโลก</h2>
                <div id="chat-list" class="chat-list"></div>
                <div class="chat-form">
                    <input id="chat-input" maxlength="240" placeholder="พิมพ์ข้อความถึงทุกคน">
                    <button id="chat-send-button" type="button">ส่ง</button>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
