import './bootstrap';
import Phaser from 'phaser';

const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const isAuthenticated = document.body.dataset.authenticated === 'true';
const tokenKey = 'dragon-path-player-token';
const browserToken = localStorage.getItem(tokenKey) || crypto.randomUUID();
localStorage.setItem(tokenKey, browserToken);
const gameFontFamily = 'Noto Sans Thai, Arial, sans-serif';

const state = {
    payload: null,
    selectedMonsterId: null,
    selectedSkillId: null,
    lastDamage: null,
    lastDice: null,
    lastEffect: null,
    lastMonsterDamage: null,
    lastMonsterDice: null,
    lastMonsterEffect: null,
    lastMonsterHp: null,
    lastMonsterId: null,
    encounterResolved: false,
    scene: null,
    mode: 'world',
    knownOnlineIds: new Set(),
    seenChatIds: new Set(),
    selectedOpponentId: null,
    pvpResolved: false,
    worldTimer: null,
    worldPlayerX: Number(localStorage.getItem('dragon-path-world-x') || 0.38),
    worldPlayerY: Number(localStorage.getItem('dragon-path-world-y') || 0.52),
    walking: false,
    autoFarm: {
        active: false,
        type: null,
        targetRounds: 1,
        completedRounds: 0,
        running: false,
        stopRequested: false,
        timer: null,
    },
};

if (Number.isNaN(state.worldPlayerX)) {
    state.worldPlayerX = 0.38;
}
if (Number.isNaN(state.worldPlayerY)) {
    state.worldPlayerY = 0.52;
}

const els = {
    saveStatus: document.getElementById('save-status'),
    toggleStatusButton: document.getElementById('toggle-status-button'),
    toggleMenuButton: document.getElementById('toggle-menu-button'),
    soundToggleButton: document.getElementById('sound-toggle-button'),
    bgmToggleButton: document.getElementById('bgm-toggle-button'),
    modeRoot: document.getElementById('mode-root'),
    playerName: document.getElementById('player-name'),
    renameButton: document.getElementById('rename-button'),
    classBadge: document.getElementById('class-badge'),
    className: document.getElementById('class-name'),
    levelText: document.getElementById('level-text'),
    atkText: document.getElementById('atk-text'),
    defText: document.getElementById('def-text'),
    specialStatsText: document.getElementById('special-stats-text'),
    hpBar: document.getElementById('hp-bar'),
    mpBar: document.getElementById('mp-bar'),
    expBar: document.getElementById('exp-bar'),
    hpText: document.getElementById('hp-text'),
    mpText: document.getElementById('mp-text'),
    expText: document.getElementById('exp-text'),
    rollEncounterButton: document.getElementById('roll-encounter-button'),
    encounterLevelDice: document.getElementById('encounter-level-dice'),
    encounterLevelEffect: document.getElementById('encounter-level-effect'),
    encounterCountDice: document.getElementById('encounter-count-dice'),
    encounterCountEffect: document.getElementById('encounter-count-effect'),
    monsterList: document.getElementById('monster-list'),
    monsterNameText: document.getElementById('monster-name-text'),
    monsterStatsText: document.getElementById('monster-stats-text'),
    monsterHpBar: document.getElementById('monster-hp-bar'),
    monsterHpText: document.getElementById('monster-hp-text'),
    skillList: document.getElementById('skill-list'),
    playerDiceFace: document.getElementById('player-dice-face'),
    playerDiceEffect: document.getElementById('player-dice-effect'),
    monsterDiceFace: document.getElementById('monster-dice-face'),
    monsterDiceEffect: document.getElementById('monster-dice-effect'),
    diceResultText: document.getElementById('dice-result-text'),
    hitEffectText: document.getElementById('hit-effect-text'),
    monsterRollText: document.getElementById('monster-roll-text'),
    monsterLastDamageText: document.getElementById('monster-last-damage-text'),
    damageEstimateText: document.getElementById('damage-estimate-text'),
    lastDamageText: document.getElementById('last-damage-text'),
    fightButton: document.getElementById('fight-button'),
    battleLog: document.getElementById('battle-log'),
    evolutionNote: document.getElementById('evolution-note'),
    classEvolutionTree: document.getElementById('class-evolution-tree'),
    classChoices: document.getElementById('class-choices'),
    elementChoices: document.getElementById('element-choices'),
    monsterModeButton: document.getElementById('monster-mode-button'),
    arenaModeButton: document.getElementById('arena-mode-button'),
    arenaPlayerList: document.getElementById('arena-player-list'),
    pvpOpponentName: document.getElementById('pvp-opponent-name'),
    pvpOpponentStats: document.getElementById('pvp-opponent-stats'),
    pvpOpponentHpBar: document.getElementById('pvp-opponent-hp-bar'),
    pvpOpponentHpText: document.getElementById('pvp-opponent-hp-text'),
    pvpFightButton: document.getElementById('pvp-fight-button'),
    botPvpButton: document.getElementById('bot-pvp-button'),
    mapMoveButtons: document.querySelectorAll('.map-move'),
    leaderboardList: document.getElementById('leaderboard-list'),
    botLeaderboardList: document.getElementById('bot-leaderboard-list'),
    chatList: document.getElementById('chat-list'),
    chatInput: document.getElementById('chat-input'),
    chatSendButton: document.getElementById('chat-send-button'),
    totalPlayersText: document.getElementById('total-players-text'),
    onlinePlayersText: document.getElementById('online-players-text'),
    autoRoundInput: document.getElementById('auto-round-input'),
    autoMonsterButton: document.getElementById('auto-monster-button'),
    autoBotButton: document.getElementById('auto-bot-button'),
    autoContinueOnceButton: document.getElementById('auto-continue-once-button'),
    autoReturnButton: document.getElementById('auto-return-button'),
    recoverButton: document.getElementById('recover-button'),
    autoStopButton: document.getElementById('auto-stop-button'),
    autoFarmStatus: document.getElementById('auto-farm-status'),
};

const elementMeta = {
    earth: { label: 'ดิน', fullLabel: 'ธาตุดิน', color: '#92400e' },
    water: { label: 'น้ำ', fullLabel: 'ธาตุน้ำ', color: '#38bdf8' },
    wind: { label: 'ลม', fullLabel: 'ธาตุลม', color: '#22c55e' },
    fire: { label: 'ไฟ', fullLabel: 'ธาตุไฟ', color: '#ef4444' },
    neutral: { label: 'ไร้ธาตุ', fullLabel: 'ไร้ธาตุ', color: '#94a3b8' },
};

const assetBasePath = '/assets/generated/pixel-art';
const playerClassRows = {
    normal: 0,
    cavalry: 1,
    dragon_knight: 1,
    eagle_warrior: 1,
    wolf_orc: 1,
    mage: 2,
    fire_mage: 2,
    ice_mage: 2,
    storm_mage: 2,
    archer: 3,
    forest_hunter: 3,
    falcon_archer: 3,
    shadow_marksman: 3,
};
const monsterFamilyRows = {
    slime: 0,
    bat: 1,
    wolf: 2,
    skeleton: 3,
    golem: 4,
    wyvern: 5,
    default: 0,
};
const mapGridColumns = 14;
const mapGridRows = 7;

const audio = {
    ctx: null,
    sfxEnabled: localStorage.getItem('dragon-path-sfx') !== 'off' && localStorage.getItem('dragon-path-audio') !== 'off',
    bgmEnabled: localStorage.getItem('dragon-path-bgm') !== 'off' && localStorage.getItem('dragon-path-audio') !== 'off',
    bgmNodes: [],
};

function ensureAudio() {
    if (!audio.ctx) {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        audio.ctx = new AudioContext();
    }

    if (audio.ctx.state === 'suspended') {
        audio.ctx.resume();
    }

    if (audio.bgmEnabled && audio.bgmNodes.length === 0) {
        startBgm();
    }
}

function startBgm() {
    if (!audio.ctx || audio.bgmNodes.length > 0) return;

    const master = audio.ctx.createGain();
    master.gain.value = 0.045;
    master.connect(audio.ctx.destination);

    [146.83, 220, 293.66].forEach((freq, index) => {
        const osc = audio.ctx.createOscillator();
        const gain = audio.ctx.createGain();
        osc.type = index === 1 ? 'triangle' : 'sine';
        osc.frequency.value = freq;
        gain.gain.value = index === 1 ? 0.22 : 0.16;
        osc.connect(gain).connect(master);
        osc.start();
        audio.bgmNodes.push(osc, gain);
    });

    audio.bgmNodes.push(master);
}

function stopBgm() {
    audio.bgmNodes.forEach((node) => {
        if (typeof node.stop === 'function') {
            try {
                node.stop();
            } catch {
                // Already stopped.
            }
        }
        if (typeof node.disconnect === 'function') {
            node.disconnect();
        }
    });
    audio.bgmNodes = [];
}

function setSfxEnabled(enabled) {
    audio.sfxEnabled = enabled;
    localStorage.setItem('dragon-path-sfx', enabled ? 'on' : 'off');
    if (els.soundToggleButton) {
        els.soundToggleButton.textContent = enabled ? 'เอฟเฟกต์: เปิด' : 'เอฟเฟกต์: ปิด';
        els.soundToggleButton.classList.toggle('selected', enabled);
    }
}

function setBgmEnabled(enabled) {
    audio.bgmEnabled = enabled;
    localStorage.setItem('dragon-path-bgm', enabled ? 'on' : 'off');
    if (els.bgmToggleButton) {
        els.bgmToggleButton.textContent = enabled ? 'เพลง: เปิด' : 'เพลง: ปิด';
        els.bgmToggleButton.classList.toggle('selected', enabled);
    }
    if (enabled) {
        ensureAudio();
    } else {
        stopBgm();
    }
}

function playSfx(type = 'click') {
    if (!audio.sfxEnabled) return;
    ensureAudio();
    if (!audio.ctx) return;

    const now = audio.ctx.currentTime;
    const osc = audio.ctx.createOscillator();
    const gain = audio.ctx.createGain();
    const presets = {
        click: [420, 0.035, 'triangle', 0.045],
        explore: [520, 0.14, 'sine', 0.07],
        hit: [110, 0.12, 'sawtooth', 0.09],
        crit: [760, 0.18, 'square', 0.08],
        victory: [660, 0.3, 'triangle', 0.1],
        chat: [880, 0.09, 'sine', 0.05],
    };
    const [freq, length, wave, volume] = presets[type] || presets.click;

    osc.type = wave;
    osc.frequency.setValueAtTime(freq, now);
    osc.frequency.exponentialRampToValueAtTime(Math.max(70, freq * 0.55), now + length);
    gain.gain.setValueAtTime(volume, now);
    gain.gain.exponentialRampToValueAtTime(0.001, now + length);
    osc.connect(gain).connect(audio.ctx.destination);
    osc.start(now);
    osc.stop(now + length);
}

class BattleScene extends Phaser.Scene {
    constructor() {
        super('BattleScene');
    }

    create() {
        state.scene = this;
        this.createTexture('playerTex', 0x56c7ff, 0xffffff);
        this.createTexture('monster_slime', 0x65d46e, 0x173f21);
        this.createTexture('monster_bat', 0x9b7cff, 0x231642);
        this.createTexture('monster_wolf', 0xb9b0a2, 0x2f2b26);
        this.createTexture('monster_skeleton', 0xe8e1cb, 0x363124);
        this.createTexture('monster_golem', 0x8e9a9d, 0x2d3436);
        this.createTexture('monster_wyvern', 0xe76f51, 0x4a1f18);
        this.createTexture('monster_default', 0xff6464, 0x2f1515);
        this.bg = this.add.rectangle(400, 250, 800, 500, 0x9fe7ff);
        this.ground = this.add.rectangle(400, 380, 800, 150, 0x67c56b).setAlpha(0.95);
        this.sun = this.add.circle(170, 130, 58, 0xffd166, 0.65);
        this.path = this.add.ellipse(410, 404, 620, 90, 0xf1d58a, 0.55);
        this.titleText = this.add.text(28, 24, 'แผนที่โลก', { fontFamily: gameFontFamily, fontSize: '18px', color: '#184a35' });
        this.player = this.add.sprite(230, 310, 'playerTex').setScale(2.2);
        this.monster = this.add.sprite(570, 300, 'monster_default').setScale(2.4);
        this.monsterName = this.add.text(500, 385, 'Monster', { fontFamily: gameFontFamily, fontSize: '18px', color: '#ffffff' });
        this.monster.setVisible(false);
        this.monsterName.setVisible(false);
        this.tweens.add({ targets: this.player, scaleX: 2.28, scaleY: 2.28, duration: 900, yoyo: true, repeat: -1, ease: 'Sine.easeInOut' });
        this.tweens.add({ targets: this.monster, scaleX: 2.5, scaleY: 2.5, duration: 1050, yoyo: true, repeat: -1, ease: 'Sine.easeInOut' });
        this.scale.on('resize', this.resizeScene, this);
        this.resizeScene({ width: this.scale.width, height: this.scale.height });
        this.setMode('world');
    }

    createTexture(key, fill, accent) {
        const g = this.make.graphics({ x: 0, y: 0 }, false);
        g.fillStyle(fill, 1);
        g.fillRoundedRect(10, 18, 44, 50, 10);
        g.fillCircle(32, 14, 18);
        g.fillStyle(accent, 1);
        g.fillCircle(25, 12, 4);
        g.fillCircle(39, 12, 4);
        g.generateTexture(key, 64, 72);
        g.destroy();
    }

    setMonster(monster) {
        if (!monster || !this.monsterName) return;
        const textureKey = `monster_${monster.sprite_key || 'default'}`;
        const element = elementMeta[monster.element || 'neutral'] || elementMeta.neutral;
        this.monster.setTexture(this.textures.exists(textureKey) ? textureKey : 'monster_default');
        this.monster.setAlpha(1).setAngle(0).setScale(2.4).setY(300);
        this.monsterName.setText(`${element.fullLabel} ${monster.name} LV ${monster.level}`);
        this.monsterName.setColor(element.color);
        this.resizeScene({ width: this.scale.width, height: this.scale.height });
    }

    setMode(mode) {
        const isWorld = mode === 'world';
        this.bg.setFillStyle(isWorld ? 0x9fe7ff : 0x14213d);
        this.ground.setFillStyle(isWorld ? 0x67c56b : 0x23401f).setAlpha(isWorld ? 0.95 : 0.85);
        this.sun.setFillStyle(isWorld ? 0xffd166 : 0x4b3b52, isWorld ? 0.65 : 0.28);
        this.path.setVisible(isWorld);
        this.titleText.setText(isWorld ? 'แผนที่โลก' : 'ดันเจี้ยน');
        this.titleText.setColor(isWorld ? '#184a35' : '#dce7ff');
        this.monster.setVisible(!isWorld);
        this.monsterName.setVisible(!isWorld);
        this.resizeScene({ width: this.scale.width, height: this.scale.height });
    }

    resizeScene(gameSize) {
        const width = gameSize.width || 800;
        const height = gameSize.height || 500;
        const groundY = height * 0.72;
        const playerX = width * 0.34;
        const monsterX = width * 0.68;
        const actorY = groundY - 46;

        this.bg.setPosition(width / 2, height / 2).setSize(width, height);
        this.ground.setPosition(width / 2, groundY + 70).setSize(width, Math.max(180, height * 0.28));
        this.sun.setPosition(width * 0.18, height * 0.24);
        this.path.setPosition(width * 0.45, groundY + 52).setSize(width * 0.55, Math.max(72, height * 0.1));
        this.titleText.setPosition(28, 96);
        this.player.setPosition(playerX, actorY);
        this.monster.setPosition(monsterX, actorY);
        this.monsterName.setPosition(monsterX - 58, actorY + 82);
    }

    playMonsterDeath(monsterName = 'Monster') {
        const deathText = this.add.text(this.monster.x, this.monster.y - 120, `${monsterName} defeated`, {
            fontFamily: gameFontFamily,
            fontSize: '28px',
            fontStyle: 'bold',
            color: '#ffd166',
            stroke: '#10131c',
            strokeThickness: 5,
        }).setOrigin(0.5);

        this.playDeathPose('monster');

        this.tweens.add({
            targets: deathText,
            y: deathText.y - 34,
            alpha: 0,
            duration: 1200,
            onComplete: () => deathText.destroy(),
        });
    }

    playHit(actor, amount = null, effect = null) {
        const attackerRole = actor === 'player' ? 'player' : 'monster';
        const targetRole = actor === 'player' ? 'monster' : 'player';
        const attacker = this.actorSprite(attackerRole);
        const target = this.actorSprite(targetRole);
        this.playJumpAttack(attackerRole);
        window.setTimeout(() => this.playHurtPose(targetRole), 120);

        if (amount !== null) {
            const isPlayerHit = actor === 'player';
            const color = this.effectColor(effect, isPlayerHit);
            const x = target?.x || (actor === 'player' ? this.monster.x : this.player.x);
            const y = (target?.y || (actor === 'player' ? this.monster.y : this.player.y)) - 86;
            const effectText = this.effectText(effect);
            const floating = this.add.text(x, y, `${amount > 0 ? `-${amount}` : 'MISS'}${effectText}`, {
                fontFamily: gameFontFamily,
                fontSize: effect === 'x2' ? '34px' : '30px',
                fontStyle: 'bold',
                color,
                stroke: '#10131c',
                strokeThickness: 4,
            }).setOrigin(0.5);

            this.tweens.add({
                targets: floating,
                y: y - 44,
                alpha: 0,
                duration: 700,
                onComplete: () => floating.destroy(),
            });
        }

        if (attacker) {
            attacker.setDepth(24);
        }
    }

    actorSprite(role) {
        return role === 'player' ? this.player : this.monster;
    }

    actorShadow(role) {
        return role === 'player' ? this.playerShadow : this.monsterShadow;
    }

    playStandPose(role, flipX = null) {
        const sprite = this.actorSprite(role);
        const shadow = this.actorShadow(role);
        if (!sprite) return;

        const scale = Math.max(Math.abs(sprite.scaleX), Math.abs(sprite.scaleY));
        sprite.clearTint();
        sprite.setAlpha(1).setAngle(0).setScale(scale);
        if (flipX !== null) {
            sprite.setFlipX(flipX);
        }
        if (shadow) {
            shadow.setAlpha(role === 'player' ? 0.22 : 0.26).setScale(1);
        }
    }

    playWalkPose(role, dx = 1) {
        const sprite = this.actorSprite(role);
        const shadow = this.actorShadow(role);
        if (!sprite) return;

        sprite.setFlipX(dx < 0);
        this.tweens.add({
            targets: sprite,
            y: sprite.y - 10,
            angle: dx < 0 ? -5 : 5,
            duration: 120,
            yoyo: true,
            repeat: 1,
            ease: 'Sine.easeInOut',
            onComplete: () => sprite.setAngle(0),
        });
        if (shadow) {
            this.tweens.add({
                targets: shadow,
                scaleX: 0.88,
                alpha: 0.12,
                duration: 120,
                yoyo: true,
                repeat: 1,
            });
        }
    }

    playJumpAttack(role) {
        const sprite = this.actorSprite(role);
        if (!sprite) return;

        const startX = sprite.x;
        const startY = sprite.y;
        const direction = role === 'player' ? 1 : -1;
        sprite.setDepth(28);
        this.tweens.add({
            targets: sprite,
            x: startX + (direction * 82),
            y: startY - 42,
            angle: direction * 8,
            duration: 150,
            ease: 'Cubic.easeOut',
            yoyo: true,
            onComplete: () => {
                sprite.setPosition(startX, startY).setAngle(0).setDepth(22);
            },
        });
    }

    playHurtPose(role) {
        const sprite = this.actorSprite(role);
        if (!sprite || !sprite.visible) return;

        const startX = sprite.x;
        const startY = sprite.y;
        const direction = role === 'player' ? -1 : 1;
        sprite.setTint(0xff6b6b);
        this.tweens.add({
            targets: sprite,
            x: startX + (direction * 18),
            y: startY + 4,
            alpha: 0.62,
            duration: 70,
            yoyo: true,
            repeat: 2,
            onComplete: () => {
                sprite.setPosition(startX, startY).setAlpha(1).clearTint();
            },
        });
    }

    playDeathPose(role) {
        const sprite = this.actorSprite(role);
        const shadow = this.actorShadow(role);
        if (!sprite) return;

        sprite.clearTint();
        this.tweens.add({
            targets: sprite,
            angle: role === 'player' ? -86 : 86,
            y: sprite.y + 54,
            alpha: 0.52,
            scaleY: sprite.scaleY * 0.74,
            duration: 520,
            ease: 'Cubic.easeIn',
        });
        if (shadow) {
            this.tweens.add({
                targets: shadow,
                alpha: 0.08,
                scaleX: 1.25,
                duration: 520,
            });
        }
    }

    effectColor(effect, isPlayerHit) {
        if (!isPlayerHit) return '#ff8fa3';
        if (effect === 'x2') return '#ffd166';
        if (effect === 'critical') return '#ff9f1c';
        if (effect === 'bleed') return '#ff4d6d';
        if (effect === 'blocked') return '#9fb3c8';
        if (effect === 'dodge') return '#c9d6df';
        return '#e6f4ff';
    }

    effectText(effect) {
        if (effect === 'x2') return ' x2';
        if (effect === 'critical') return ' CRIT';
        if (effect === 'bleed') return ' BLEED';
        if (effect === 'blocked') return ' BLOCK';
        if (effect === 'dodge') return ' DODGE';
        return '';
    }

    showSpeech(message, side = 'player', playerId = null) {
        const onlineActor = playerId ? this.onlineActors?.get(String(playerId)) : null;
        const target = onlineActor?.sprite || (side === 'player' ? this.player : this.monster);
        const bubble = this.add.text(target.x, target.y - 132, message, {
            fontFamily: gameFontFamily,
            fontSize: '17px',
            color: '#10131c',
            backgroundColor: '#ffffff',
            padding: { left: 12, right: 12, top: 8, bottom: 8 },
            wordWrap: { width: 260 },
        }).setOrigin(0.5).setDepth(40);

        this.tweens.add({
            targets: bubble,
            y: bubble.y - 18,
            alpha: 0,
            duration: 5000,
            ease: 'Sine.easeIn',
            onComplete: () => bubble.destroy(),
        });
    }
}

BattleScene.prototype.preload = function preloadPixelArtAssets() {
    this.load.image('playerSheet', `${assetBasePath}/player-classes-sheet.png`);
    this.load.image('monsterSheet', `${assetBasePath}/monster-evolution-sheet.png`);
    this.load.image('tileSheet', `${assetBasePath}/world-dungeon-tileset.png`);
    this.load.image('uiSheet', `${assetBasePath}/fantasy-ui-pack.png`);
};

BattleScene.prototype.createCroppedTexture = function createCroppedTexture(key, sheetKey, x, y, width, height, trimSprite = false) {
    if (this.textures.exists(key) || !this.textures.exists(sheetKey)) {
        return;
    }

    const source = this.textures.get(sheetKey).getSourceImage();
    const cropWidth = Math.ceil(width);
    const cropHeight = Math.ceil(height);
    const scratch = document.createElement('canvas');
    scratch.width = cropWidth;
    scratch.height = cropHeight;
    const scratchContext = scratch.getContext('2d', { willReadFrequently: trimSprite });
    scratchContext.clearRect(0, 0, cropWidth, cropHeight);
    scratchContext.drawImage(source, x, y, width, height, 0, 0, cropWidth, cropHeight);

    const bounds = trimSprite ? this.findPrimaryAlphaBounds(scratchContext, cropWidth, cropHeight) : null;
    const outputWidth = bounds ? bounds.width : cropWidth;
    const outputHeight = bounds ? bounds.height : cropHeight;
    const texture = this.textures.createCanvas(key, outputWidth, outputHeight);
    const context = texture.getContext();
    context.clearRect(0, 0, outputWidth, outputHeight);
    if (bounds) {
        context.drawImage(scratch, bounds.x, bounds.y, bounds.width, bounds.height, 0, 0, outputWidth, outputHeight);
    } else {
        context.drawImage(scratch, 0, 0);
    }
    texture.refresh();
};

BattleScene.prototype.findPrimaryAlphaBounds = function findPrimaryAlphaBounds(context, width, height) {
    const { data } = context.getImageData(0, 0, width, height);
    const visited = new Uint8Array(width * height);
    let best = null;

    const hasAlpha = (index) => data[(index * 4) + 3] > 18;

    for (let start = 0; start < visited.length; start++) {
        if (visited[start] || !hasAlpha(start)) continue;

        let count = 0;
        let minX = width;
        let minY = height;
        let maxX = 0;
        let maxY = 0;
        const stack = [start];
        visited[start] = 1;

        while (stack.length > 0) {
            const index = stack.pop();
            const px = index % width;
            const py = Math.floor(index / width);
            count += 1;
            minX = Math.min(minX, px);
            minY = Math.min(minY, py);
            maxX = Math.max(maxX, px);
            maxY = Math.max(maxY, py);

            const neighbors = [index - 1, index + 1, index - width, index + width];
            neighbors.forEach((next) => {
                if (next < 0 || next >= visited.length || visited[next] || !hasAlpha(next)) return;
                const nx = next % width;
                if (Math.abs(nx - px) > 1) return;
                visited[next] = 1;
                stack.push(next);
            });
        }

        if (!best || count > best.count) {
            best = { count, minX, minY, maxX, maxY };
        }
    }

    if (!best || best.count < 24) return null;

    const padding = 4;
    const x = Math.max(0, best.minX - padding);
    const y = Math.max(0, best.minY - padding);
    const maxX = Math.min(width - 1, best.maxX + padding);
    const maxY = Math.min(height - 1, best.maxY + padding);

    return {
        x,
        y,
        width: maxX - x + 1,
        height: maxY - y + 1,
    };
};

BattleScene.prototype.createTileTextures = function createTileTextures() {
    if (!this.textures.exists('tileSheet')) return;

    const cell = 128;
    const tiles = {
        tile_grass: [0, 0],
        tile_flower: [4, 0],
        tile_path: [5, 0],
        tile_bush: [0, 1],
        tile_tree: [8, 1],
        tile_water: [4, 2],
        tile_dungeon: [0, 3],
        tile_lava: [6, 4],
        tile_colosseum: [4, 5],
        prop_chest: [0, 7],
        prop_portal: [7, 7],
        prop_torch: [8, 7],
    };

    Object.entries(tiles).forEach(([key, [col, row]]) => {
        this.createCroppedTexture(key, 'tileSheet', col * cell, row * cell, cell, cell);
    });
};

BattleScene.prototype.createPlayerTexture = function createPlayerTexture() {
    if (this.textures.exists('playerSheet')) {
        for (let row = 0; row < 4; row++) {
            for (let frame = 0; frame < 4; frame++) {
                this.createCroppedTexture(`player_class_${row}_${frame}`, 'playerSheet', frame * 384, row * 256, 384, 256, true);
            }
        }
        this.createCroppedTexture('playerTex', 'playerSheet', 0, 0, 384, 256, true);
        return;
    }

    const g = this.make.graphics({ x: 0, y: 0 }, false);
    g.fillStyle(0x1d4ed8, 1);
    g.fillRoundedRect(22, 34, 44, 48, 10);
    g.fillStyle(0xf3c98b, 1);
    g.fillCircle(44, 23, 19);
    g.fillStyle(0x172554, 1);
    g.fillRoundedRect(25, 8, 38, 16, 8);
    g.fillStyle(0xffffff, 1);
    g.fillCircle(37, 21, 4);
    g.fillCircle(51, 21, 4);
    g.fillStyle(0x0f172a, 1);
    g.fillCircle(38, 21, 2);
    g.fillCircle(52, 21, 2);
    g.lineStyle(7, 0xd9e7ff, 1);
    g.lineBetween(68, 35, 92, 14);
    g.lineStyle(4, 0x38bdf8, 1);
    g.lineBetween(68, 36, 90, 16);
    g.fillStyle(0x172554, 1);
    g.fillRoundedRect(26, 80, 13, 24, 5);
    g.fillRoundedRect(51, 80, 13, 24, 5);
    g.fillStyle(0x60a5fa, 1);
    g.fillCircle(21, 53, 10);
    g.fillCircle(68, 53, 10);
    g.generateTexture('playerTex', 112, 112);
    g.destroy();
};

BattleScene.prototype.createMonsterTexture = function createMonsterTexture(key, fill, accent, type = 'slime') {
    if (this.textures.exists('monsterSheet')) {
        const row = monsterFamilyRows[type] ?? monsterFamilyRows.default;
        const frameWidth = 384;
        const frameHeight = 1024 / 6;

        for (let stage = 0; stage < 4; stage++) {
            this.createCroppedTexture(`monster_${type}_${stage}`, 'monsterSheet', stage * frameWidth, row * frameHeight, frameWidth, frameHeight, true);
        }
        this.createCroppedTexture(key, 'monsterSheet', 0, row * frameHeight, frameWidth, frameHeight, true);
        return;
    }

    const g = this.make.graphics({ x: 0, y: 0 }, false);
    g.fillStyle(fill, 1);
    if (type === 'bat') {
        g.fillTriangle(12, 44, 42, 18, 42, 62);
        g.fillTriangle(116, 44, 86, 18, 86, 62);
        g.fillRoundedRect(42, 22, 44, 58, 18);
    } else if (type === 'wolf') {
        g.fillRoundedRect(26, 34, 76, 42, 14);
        g.fillTriangle(38, 34, 48, 10, 58, 36);
        g.fillTriangle(78, 34, 90, 10, 94, 38);
        g.fillRect(36, 72, 13, 26);
        g.fillRect(82, 72, 13, 26);
    } else if (type === 'skeleton') {
        g.fillCircle(64, 26, 24);
        g.fillRoundedRect(42, 52, 44, 44, 8);
        g.lineStyle(6, fill, 1);
        g.lineBetween(28, 60, 12, 88);
        g.lineBetween(100, 60, 116, 88);
    } else if (type === 'golem') {
        g.fillRoundedRect(28, 18, 72, 76, 12);
        g.fillStyle(accent, 1);
        g.fillRoundedRect(40, 28, 48, 18, 6);
        g.fillStyle(fill, 1);
        g.fillRect(16, 48, 18, 42);
        g.fillRect(96, 48, 18, 42);
    } else if (type === 'wyvern') {
        g.fillTriangle(18, 44, 58, 12, 52, 66);
        g.fillTriangle(110, 44, 70, 12, 76, 66);
        g.fillRoundedRect(46, 28, 38, 58, 18);
        g.fillTriangle(62, 18, 76, 0, 82, 24);
    } else {
        g.fillEllipse(64, 64, 86, 66);
        g.fillCircle(64, 38, 34);
    }
    g.fillStyle(accent, 1);
    g.fillCircle(52, 42, 5);
    g.fillCircle(76, 42, 5);
    g.lineStyle(4, accent, 1);
    g.lineBetween(52, 64, 76, 64);
    g.generateTexture(key, 128, 112);
    g.destroy();
};

BattleScene.prototype.createSceneLayers = function createSceneLayers() {
    this.createTileTextures();
    this.bg = this.add.rectangle(400, 250, 800, 500, 0x7dd3fc);
    this.sun = this.add.circle(170, 130, 58, 0xffd166, 0.72);
    this.worldBaseTiles = [];
    this.dungeonBaseTiles = [];
    this.pvpBaseTiles = [];
    this.worldTiles = [];
    this.dungeonTiles = [];
    this.pvpTiles = [];
    for (let row = 0; row < mapGridRows; row++) {
        for (let col = 0; col < mapGridColumns; col++) {
            const midRow = Math.floor(mapGridRows / 2);
            const midCol = Math.floor(mapGridColumns / 2);
            const isMainPath = row === midRow || col === midCol || (col % 5 === 1 && row > midRow);
            const grassColors = [0x2f9f4b, 0x37b65a, 0x2d8f45, 0x47a861];
            const dungeonColors = [0x20212d, 0x292b3a, 0x34313b, 0x3a2831];
            const pvpColors = [0xb9853b, 0xc89a52, 0xa97938, 0xd0a45e];
            const worldBase = isMainPath ? 0xcfa45a : grassColors[(row + col) % grassColors.length];
            const dungeonBase = (row + col) % 9 === 0 ? 0x5f1d1d : dungeonColors[(row * 2 + col) % dungeonColors.length];
            const pvpBase = pvpColors[(row + col * 2) % pvpColors.length];
            const worldKey = isMainPath ? 'tile_path' : ((row + col) % 5 === 0 ? 'tile_flower' : 'tile_grass');

            this.worldBaseTiles.push(this.add.rectangle(0, 0, 100, 100, worldBase, 1).setDepth(0.5));
            this.dungeonBaseTiles.push(this.add.rectangle(0, 0, 100, 100, dungeonBase, 0).setDepth(0.5));
            this.pvpBaseTiles.push(this.add.rectangle(0, 0, 100, 100, pvpBase, 0).setDepth(0.5));
            this.worldTiles.push(this.add.image(0, 0, worldKey).setAlpha(0.66).setDepth(1));
            this.dungeonTiles.push(this.add.image(0, 0, (row + col) % 7 === 0 ? 'tile_lava' : 'tile_dungeon').setAlpha(0).setDepth(1));
            this.pvpTiles.push(this.add.image(0, 0, 'tile_colosseum').setAlpha(0).setDepth(1));
        }
    }
    this.sceneProps = {
        portal: this.add.image(0, 0, 'prop_portal').setScale(1.08).setDepth(4),
        chest: this.add.image(0, 0, 'prop_chest').setScale(0.9).setDepth(4),
        torchLeft: this.add.image(0, 0, 'prop_torch').setScale(0.8).setDepth(4).setAlpha(0),
        torchRight: this.add.image(0, 0, 'prop_torch').setScale(0.8).setDepth(4).setAlpha(0),
    };
    this.mountainBack = this.add.triangle(320, 278, 0, 150, 210, 20, 420, 150, 0x5b8fb9, 0.78);
    this.mountainFront = this.add.triangle(540, 288, 0, 160, 240, 16, 480, 160, 0x3f6f8f, 0.88);
    this.clouds = [
        this.add.ellipse(260, 138, 170, 42, 0xffffff, 0.45),
        this.add.ellipse(760, 112, 220, 48, 0xffffff, 0.36),
    ];
    this.trees = [];
    for (let i = 0; i < 11; i++) {
        const x = 70 + i * 150;
        this.trees.push(this.add.rectangle(x, 388, 18, 72, 0x6b3f1d));
        this.trees.push(this.add.triangle(x, 326, 0, 78, 50, 0, 100, 78, 0x166534));
    }
    this.mapTiles = [];
    for (let row = 0; row < 4; row++) {
        for (let col = 0; col < 6; col++) {
            const isPath = row === 2 || col === 2;
            const tile = this.add.rectangle(0, 0, 58, 42, isPath ? 0xd9b56d : 0x3fbf5a, isPath ? 0.78 : 0.46)
                .setStrokeStyle(2, 0xffffff, 0.14);
            this.mapTiles.push({ tile, row, col });
        }
    }
    this.ground = this.add.rectangle(400, 380, 800, 150, 0x2f8f46).setAlpha(0.98).setDepth(0);
    this.path = this.add.ellipse(410, 426, 620, 100, 0xcfa968, 0.7);
    this.dungeonGlow = this.add.rectangle(400, 250, 800, 500, 0x090a12, 0).setBlendMode(Phaser.BlendModes.MULTIPLY);
    this.torches = [
        this.add.circle(120, 260, 18, 0xffa02b, 0),
        this.add.circle(680, 260, 18, 0xffa02b, 0),
    ];
    this.colosseum = {
        skyGlow: this.add.ellipse(400, 148, 780, 180, 0xffdf9a, 0),
        rearWall: this.add.rectangle(400, 262, 820, 180, 0xc8a46b, 0),
        upperTier: this.add.rectangle(400, 196, 860, 62, 0x9b6b3e, 0),
        arenaSand: this.add.ellipse(400, 410, 700, 150, 0xd6ad63, 0),
        flags: [],
        arches: [],
    };
    for (let i = 0; i < 14; i++) {
        this.colosseum.arches.push(this.add.ellipse(70 + i * 62, 262, 34, 86, 0x6d4b32, 0));
        this.colosseum.arches.push(this.add.rectangle(70 + i * 62, 218, 34, 12, 0xf0d08f, 0));
    }
    for (let i = 0; i < 8; i++) {
        this.colosseum.flags.push(this.add.triangle(130 + i * 92, 144, 0, 0, 34, 10, 0, 20, i % 2 ? 0xdc2626 : 0xfacc15, 0));
    }
    this.titleText = this.add.text(28, 94, 'แผนที่โลก', {
        fontFamily: gameFontFamily,
        fontSize: '22px',
        fontStyle: 'bold',
        color: '#f8fafc',
        stroke: '#0f172a',
        strokeThickness: 5,
    });
};

BattleScene.prototype.create = function createGameScene() {
    state.scene = this;
    this.createPlayerTexture();
    this.createMonsterTexture('monster_slime', 0x65d46e, 0x173f21, 'slime');
    this.createMonsterTexture('monster_bat', 0x9b7cff, 0x231642, 'bat');
    this.createMonsterTexture('monster_wolf', 0xb9b0a2, 0x2f2b26, 'wolf');
    this.createMonsterTexture('monster_skeleton', 0xe8e1cb, 0x363124, 'skeleton');
    this.createMonsterTexture('monster_golem', 0x8e9a9d, 0x2d3436, 'golem');
    this.createMonsterTexture('monster_wyvern', 0xe76f51, 0x4a1f18, 'wyvern');
    this.createMonsterTexture('monster_default', 0xff6464, 0x2f1515, 'slime');
    this.createSceneLayers();
    this.playerShadow = this.add.ellipse(230, 374, 128, 26, 0x000000, 0.22).setDepth(18);
    this.monsterShadow = this.add.ellipse(570, 374, 142, 28, 0x000000, 0.26).setDepth(18);
    this.player = this.add.sprite(230, 310, 'playerTex').setScale(this.textures.exists('playerSheet') ? 0.76 : 2.1).setDepth(22);
    this.monster = this.add.sprite(570, 300, 'monster_default').setScale(this.textures.exists('monsterSheet') ? 0.82 : 2.25).setDepth(22);
    this.playerNameTag = this.add.text(230, 190, 'You', {
        fontFamily: gameFontFamily,
        fontSize: '16px',
        fontStyle: 'bold',
        color: '#ffffff',
        stroke: '#0f172a',
        strokeThickness: 4,
    }).setOrigin(0.5).setDepth(30);
    this.monsterName = this.add.text(500, 385, 'Monster', {
        fontFamily: gameFontFamily,
        fontSize: '18px',
        fontStyle: 'bold',
        color: '#ffffff',
        stroke: '#111827',
        strokeThickness: 4,
    }).setOrigin(0.5).setPadding(10, 5, 10, 5).setBackgroundColor('rgba(8, 13, 23, 0.72)').setDepth(30);
    this.monster.setVisible(false);
    this.monsterShadow.setVisible(false);
    this.monsterName.setVisible(false);
    this.onlineActors = new Map();
    this.tweens.add({ targets: this.player, y: '+=8', duration: 1000, yoyo: true, repeat: -1, ease: 'Sine.easeInOut' });
    this.tweens.add({ targets: this.monster, y: '+=10', duration: 920, yoyo: true, repeat: -1, ease: 'Sine.easeInOut' });
    this.scale.on('resize', this.resizeScene, this);
    this.resizeScene({ width: this.scale.width, height: this.scale.height });
    this.setMode('world');
};

BattleScene.prototype.setMode = function setGameSceneMode(mode) {
    this.currentMode = mode;
    const isWorld = mode === 'world';
    const isPvp = mode === 'pvp';
    this.playStandPose('player', isWorld ? null : false);
    this.bg.setFillStyle(isWorld ? 0x7dd3fc : (isPvp ? 0x26435f : 0x101827));
    this.ground.setFillStyle(isWorld ? 0x2f8f46 : (isPvp ? 0xb9853b : 0x20212d)).setAlpha(isWorld ? 0.98 : 0.96);
    this.sun.setFillStyle(isWorld ? 0xffd166 : (isPvp ? 0xffc66d : 0x6d597a), isWorld || isPvp ? 0.72 : 0.28);
    this.path.setVisible(false);
    this.mountainBack.setVisible(false);
    this.mountainFront.setVisible(false);
    this.clouds.forEach((cloud) => cloud.setVisible(false));
    this.trees.forEach((tree) => tree.setVisible(false));
    this.mapTiles.forEach(({ tile }) => tile.setVisible(false));
    this.worldBaseTiles?.forEach((tile) => tile.setAlpha(isWorld ? 1 : 0));
    this.dungeonBaseTiles?.forEach((tile) => tile.setAlpha(!isWorld && !isPvp ? 1 : 0));
    this.pvpBaseTiles?.forEach((tile) => tile.setAlpha(isPvp ? 1 : 0));
    this.worldTiles?.forEach((tile) => tile.setAlpha(isWorld ? 0.66 : 0));
    this.dungeonTiles?.forEach((tile) => tile.setAlpha(!isWorld && !isPvp ? 0.62 : 0));
    this.pvpTiles?.forEach((tile) => tile.setAlpha(isPvp ? 0.58 : 0));
    this.sceneProps?.portal.setAlpha(isWorld ? 0.95 : 0);
    this.sceneProps?.chest.setAlpha(isWorld ? 0.95 : 0);
    this.sceneProps?.torchLeft.setAlpha(!isWorld && !isPvp ? 0.96 : 0);
    this.sceneProps?.torchRight.setAlpha(!isWorld && !isPvp ? 0.96 : 0);
    this.dungeonGlow.setAlpha(isWorld || isPvp ? 0 : 0.48);
    this.torches.forEach((torch) => torch.setAlpha(!isWorld && !isPvp ? 0.75 : 0));
    this.colosseum.skyGlow.setAlpha(isPvp ? 0.42 : 0);
    this.colosseum.rearWall.setAlpha(isPvp ? 0.94 : 0);
    this.colosseum.upperTier.setAlpha(isPvp ? 0.92 : 0);
    this.colosseum.arenaSand.setAlpha(isPvp ? 0.84 : 0);
    this.colosseum.arches.forEach((arch) => arch.setAlpha(isPvp ? 0.92 : 0));
    this.colosseum.flags.forEach((flag) => flag.setAlpha(isPvp ? 0.9 : 0));
    this.titleText.setText(isWorld ? 'แผนที่โลก' : (isPvp ? 'ลานประลอง' : 'ดันเจี้ยน'));
    this.playerNameTag.setVisible(isWorld);
    this.playerShadow.setVisible(true);
    this.player.setVisible(true);
    this.monster.setVisible(!isWorld);
    this.monsterShadow.setVisible(!isWorld);
    this.monsterName.setVisible(!isWorld);
    this.positionOnlineActors(this.scale.width, this.scale.height * 0.68 - 72);
    this.resizeScene({ width: this.scale.width, height: this.scale.height });
};

BattleScene.prototype.resizeScene = function resizeGameScene(gameSize) {
    const width = gameSize.width || 800;
    const height = gameSize.height || 500;
    const groundY = height * 0.68;
    const mapTop = Math.max(124, height * 0.22);
    const mapHeight = Math.max(280, height - mapTop + 28);
    const tileWidth = Math.ceil(width / mapGridColumns) + 2;
    const tileHeight = Math.ceil(mapHeight / mapGridRows) + 2;
    const playerX = width * (this.currentMode === 'world' ? state.worldPlayerX : 0.38);
    const monsterX = width * 0.62;
    const actorY = this.currentMode === 'world'
        ? Phaser.Math.Linear(groundY - 150, groundY - 20, state.worldPlayerY)
        : groundY - 72;

    this.bg.setPosition(width / 2, height / 2).setSize(width, height);
    this.ground.setPosition(width / 2, groundY + 90).setSize(width, Math.max(230, height * 0.34));
    this.sun.setPosition(width * 0.18, height * 0.2);
    const positionMapTile = (tile, index, isImage = false) => {
        const row = Math.floor(index / mapGridColumns);
        const col = index % mapGridColumns;
        const x = (col * tileWidth) + (tileWidth / 2) - 1;
        const y = mapTop + (row * tileHeight) + (tileHeight / 2) - 1;

        tile.setPosition(x, y);
        if (isImage) {
            tile.setDisplaySize(tileWidth + 4, tileHeight + 4);
        } else {
            tile.setSize(tileWidth + 4, tileHeight + 4);
        }
    };
    this.worldBaseTiles?.forEach((tile, index) => positionMapTile(tile, index));
    this.dungeonBaseTiles?.forEach((tile, index) => positionMapTile(tile, index));
    this.pvpBaseTiles?.forEach((tile, index) => positionMapTile(tile, index));
    this.worldTiles?.forEach((tile, index) => {
        positionMapTile(tile, index, true);
    });
    this.dungeonTiles?.forEach((tile, index) => {
        positionMapTile(tile, index, true);
    });
    this.pvpTiles?.forEach((tile, index) => {
        positionMapTile(tile, index, true);
    });
    const propSize = Math.max(76, Math.min(124, tileWidth * 1.2));
    this.sceneProps?.portal.setPosition(width * 0.77, groundY - 92).setDisplaySize(propSize, propSize);
    this.sceneProps?.chest.setPosition(width * 0.2, groundY - 48).setDisplaySize(propSize * 0.76, propSize * 0.76);
    this.sceneProps?.torchLeft.setPosition(width * 0.24, groundY - 114).setDisplaySize(propSize * 0.72, propSize * 0.72);
    this.sceneProps?.torchRight.setPosition(width * 0.76, groundY - 114).setDisplaySize(propSize * 0.72, propSize * 0.72);
    this.mountainBack.setPosition(width * 0.35, groundY - 150).setScale(Math.max(1.2, width / 900), 1.2);
    this.mountainFront.setPosition(width * 0.63, groundY - 135).setScale(Math.max(1.15, width / 950), 1.15);
    this.clouds[0].setPosition(width * 0.28, height * 0.2);
    this.clouds[1].setPosition(width * 0.72, height * 0.16);
    this.trees.forEach((tree, index) => {
        const pair = Math.floor(index / 2);
        const x = ((pair * 160) % (width + 220)) - 80;
        tree.setPosition(x, index % 2 === 0 ? groundY + 28 : groundY - 24);
    });
    this.mapTiles.forEach(({ tile, row, col }) => {
        tile.setPosition(width * 0.33 + col * 64, groundY - 166 + row * 48);
        tile.setSize(Math.max(44, width * 0.035), 38);
    });
    this.path.setPosition(width / 2, groundY + 68).setSize(width * 0.46, Math.max(82, height * 0.11));
    this.dungeonGlow.setPosition(width / 2, height / 2).setSize(width, height);
    this.torches[0].setPosition(width * 0.26, groundY - 84);
    this.torches[1].setPosition(width * 0.74, groundY - 84);
    this.colosseum.skyGlow.setPosition(width / 2, height * 0.18).setSize(width * 0.78, height * 0.2);
    this.colosseum.upperTier.setPosition(width / 2, height * 0.24).setSize(width * 0.78, 70);
    this.colosseum.rearWall.setPosition(width / 2, height * 0.36).setSize(width * 0.86, Math.max(154, height * 0.2));
    this.colosseum.arenaSand.setPosition(width / 2, groundY + 74).setSize(width * 0.55, Math.max(116, height * 0.16));
    this.colosseum.arches.forEach((arch, index) => {
        const pair = Math.floor(index / 2);
        const x = width * 0.12 + pair * ((width * 0.76) / 13);
        arch.setPosition(x, index % 2 === 0 ? height * 0.36 : height * 0.29);
    });
    this.colosseum.flags.forEach((flag, index) => {
        flag.setPosition(width * 0.2 + index * ((width * 0.6) / 7), height * 0.18);
    });
    this.titleText.setPosition(Math.max(350, width * 0.28), 172);
    this.playerShadow.setPosition(playerX, actorY + 88);
    this.monsterShadow.setPosition(monsterX, actorY + 88);
    this.player.setPosition(playerX, actorY);
    this.playerNameTag.setPosition(playerX, actorY - 118);
    this.monster.setPosition(monsterX, actorY);
    this.monsterName.setPosition(monsterX, actorY - 150);
    this.positionOnlineActors(width, actorY);
};

BattleScene.prototype.setPlayerName = function setPlayerName(name) {
    this.playerNameTag?.setText(name || 'Adventurer');
};

BattleScene.prototype.setPlayerClass = function setPlayerClass(classId = 'normal') {
    if (!this.player) return;

    const row = playerClassRows[classId] ?? 0;
    const textureKey = `player_class_${row}_0`;
    if (this.textures.exists(textureKey)) {
        this.player.setTexture(textureKey).setScale(row === 1 ? 0.72 : 0.76).setFlipX(false);
    }
};

BattleScene.prototype.setMonster = function setPixelMonster(monster) {
    if (!monster || !this.monsterName) return;

    const family = monster.sprite_key || monster.family_key || 'default';
    const stage = Math.max(0, Math.min(3, Number(monster.evolution_stage ?? Math.floor((monster.level || 1) / 10))));
    const textureKey = `monster_${family}_${stage}`;
    const fallbackKey = `monster_${family}`;
    const element = elementMeta[monster.element || 'neutral'] || elementMeta.neutral;

    if (this.textures.exists(textureKey)) {
        this.monster.setTexture(textureKey);
    } else if (this.textures.exists(fallbackKey)) {
        this.monster.setTexture(fallbackKey);
    } else {
        this.monster.setTexture('monster_default');
    }

    this.monster.clearTint();
    this.monster.setFlipX(false);
    this.monster.setAlpha(1).setAngle(0).setScale(this.textures.exists('monsterSheet') ? (monster.is_boss ? 1.05 : 0.88) : 2.4);
    this.playStandPose('monster', false);
    this.monsterName.setText(`${element.fullLabel} ${monster.name} LV ${monster.level}`);
    this.monsterName.setColor(element.color);
    this.resizeScene({ width: this.scale.width, height: this.scale.height });
};

BattleScene.prototype.setOpponent = function setOpponent(opponent) {
    if (!opponent || !this.monsterName) return;

    const row = playerClassRows[opponent.class_id || 'normal'] ?? 0;
    const textureKey = `player_class_${row}_0`;
    const element = elementMeta[opponent.element || 'neutral'] || elementMeta.neutral;

    if (this.textures.exists(textureKey)) {
        this.monster.setTexture(textureKey).setScale(row === 1 ? 0.72 : 0.76).setFlipX(true);
    }

    this.monster.clearTint();
    this.monster.setFlipX(true);
    this.playStandPose('monster', true);
    if (opponent.bot) {
        this.monster.setTint(0xfbbf24);
    }
    this.monster.setAlpha(1).setAngle(0);
    this.monsterName.setText(`${element.fullLabel} ${opponent.name} LV ${opponent.level}`);
    this.monsterName.setColor(element.color);
    this.resizeScene({ width: this.scale.width, height: this.scale.height });
};

BattleScene.prototype.moveWorldPlayer = function moveWorldPlayer(dx, dy = 0) {
    if (this.currentMode !== 'world') return;

    state.worldPlayerX = Math.max(0.24, Math.min(0.76, state.worldPlayerX + (dx * 0.055)));
    state.worldPlayerY = Math.max(0.12, Math.min(0.95, state.worldPlayerY + (dy * 0.16)));
    localStorage.setItem('dragon-path-world-x', String(state.worldPlayerX));
    localStorage.setItem('dragon-path-world-y', String(state.worldPlayerY));
    this.resizeScene({ width: this.scale.width, height: this.scale.height });
    this.playWalkPose('player', dx || 1);
};

BattleScene.prototype.setOnlinePlayers = function setOnlinePlayers(players = []) {
    if (!this.onlineActors) return;

    const activeIds = new Set(players.map((player) => String(player.id)));
    this.onlineActors.forEach((actor, id) => {
        if (!activeIds.has(id)) {
            actor.shadow.destroy();
            actor.sprite.destroy();
            actor.nameTag.destroy();
            this.onlineActors.delete(id);
        }
    });

    players.forEach((player) => {
        const id = String(player.id);
        if (!this.onlineActors.has(id)) {
            const sprite = this.add.sprite(0, 0, 'playerTex').setScale(this.textures.exists('playerSheet') ? 0.52 : 1.55).setDepth(22);
            const shadow = this.add.ellipse(0, 0, 88, 20, 0x000000, 0.18).setDepth(18);
            const nameTag = this.add.text(0, 0, player.name, {
                fontFamily: gameFontFamily,
                fontSize: '14px',
                fontStyle: 'bold',
                color: '#e0f2fe',
                stroke: '#0f172a',
                strokeThickness: 4,
            }).setOrigin(0.5).setDepth(30);

            this.applyOnlinePlayerTexture(sprite, player);
            sprite.setTint(this.onlinePlayerTint(player));
            this.onlineActors.set(id, { player, sprite, shadow, nameTag });
        } else {
            const actor = this.onlineActors.get(id);
            actor.player = player;
            actor.nameTag.setText(player.name);
            this.applyOnlinePlayerTexture(actor.sprite, player);
            actor.sprite.setTint(this.onlinePlayerTint(player));
        }
    });

    this.positionOnlineActors(this.scale.width, this.scale.height * 0.68 - 72);
};

BattleScene.prototype.applyOnlinePlayerTexture = function applyOnlinePlayerTexture(sprite, player) {
    const row = playerClassRows[player?.class_id || 'normal'] ?? 0;
    const textureKey = `player_class_${row}_0`;
    if (this.textures.exists(textureKey)) {
        sprite.setTexture(textureKey).setScale(row === 1 ? 0.48 : 0.52);
    }
};

BattleScene.prototype.onlinePlayerTint = function onlinePlayerTint(player) {
    const tints = [0x60a5fa, 0x34d399, 0xfbbf24, 0xf472b6, 0xa78bfa, 0x2dd4bf];
    return tints[Number(player.id) % tints.length];
};

BattleScene.prototype.positionOnlineActors = function positionOnlineActors(width, actorY) {
    if (!this.onlineActors) return;

    const isWorld = this.currentMode === 'world';
    let index = 0;
    this.onlineActors.forEach((actor) => {
        const base = 0.28 + ((Number(actor.player.id) * 0.137) % 0.44);
        const rowOffset = (index % 3) * 22;
        const x = width * base;
        const y = actorY + 34 + rowOffset;
        actor.shadow.setPosition(x, y + 76).setVisible(isWorld);
        actor.sprite.setPosition(x, y).setVisible(isWorld);
        actor.nameTag.setPosition(x, y - 104).setVisible(isWorld);
        index += 1;
    });
};

new Phaser.Game({
    type: Phaser.AUTO,
    parent: 'game-canvas',
    width: 800,
    height: 500,
    backgroundColor: '#101827',
    scale: {
        mode: Phaser.Scale.RESIZE,
        autoCenter: Phaser.Scale.CENTER_BOTH,
    },
    scene: BattleScene,
});

function csrfRequestOptions(options = {}) {
    const headers = {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
        ...(options.headers || {}),
    };
    const normalized = {
        credentials: 'same-origin',
        ...options,
        headers,
    };
    const method = String(normalized.method || 'GET').toUpperCase();
    const contentType = String(headers['Content-Type'] || headers['content-type'] || '');

    if (method !== 'GET' && typeof normalized.body === 'string' && contentType.includes('application/json')) {
        try {
            const payload = JSON.parse(normalized.body || '{}');
            if (payload && typeof payload === 'object' && !Array.isArray(payload) && !payload._token) {
                normalized.body = JSON.stringify({ _token: csrfToken, ...payload });
            }
        } catch {
            // Keep the original body if a caller sends custom JSON text.
        }
    }

    return normalized;
}

async function request(url, options = {}) {
    els.saveStatus.textContent = 'Saving';
    const response = await fetch(url, csrfRequestOptions(options));

    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        els.saveStatus.textContent = response.status === 401 ? 'Login required' : 'Error';
        if (response.status === 419) {
            throw new Error('Session หมดอายุหรือ CSRF ไม่ตรง กรุณา refresh หน้าเกมแล้วลองใหม่');
        }
        throw new Error(data.message || 'Request failed');
    }

    els.saveStatus.textContent = 'Saved';
    return data;
}

async function bootstrap() {
    const payload = await request('/game/bootstrap', {
        method: 'POST',
        body: JSON.stringify({ browserToken }),
    });
    setPayload(payload);
    startWorldPolling();
    log('ยินดีต้อนรับเข้าสู่ Dragon Path Online 0.1');
}

async function ensurePayload() {
    if (state.payload) {
        return;
    }

    const payload = await request('/game/bootstrap', {
        method: 'POST',
        body: JSON.stringify({ browserToken }),
    });
    setPayload(payload);
}

function setPayload(payload) {
    state.payload = payload;
    if (payload.encounter) {
        state.encounterResolved = false;
    }
    const livingMonsters = payload.encounter?.monsters?.filter((monster) => monster.current_hp > 0) || [];
    state.selectedMonsterId = livingMonsters.some((monster) => monster.id === state.selectedMonsterId)
        ? state.selectedMonsterId
        : livingMonsters[0]?.id ?? null;
    state.selectedSkillId = payload.skills.some((skill) => skill.id === state.selectedSkillId)
        ? state.selectedSkillId
        : payload.skills[0]?.id ?? null;
    const onlinePlayers = payload.world?.onlinePlayers || [];
    state.selectedOpponentId = payload.pvp?.opponent?.id
        || (onlinePlayers.some((player) => player.id === state.selectedOpponentId) ? state.selectedOpponentId : null);
    rememberWorldState(payload.world || {}, false);
    render();
}

function render() {
    const { player, class: currentClass, nextExp, encounter, skills, availableEvolutions, classEvolutionTree } = state.payload;
    const monsters = encounter?.monsters || [];
    const livingMonsters = monsters.filter((monster) => monster.current_hp > 0);
    els.fightButton.disabled = livingMonsters.length === 0 || state.encounterResolved;
    els.playerName.value = player.name;
    els.classBadge.textContent = '';
    els.classBadge.title = currentClass?.name || player.class_id;
    els.classBadge.style.backgroundImage = `url("${assetBasePath}/player-classes-sheet.png")`;
    els.classBadge.style.backgroundSize = '400% 400%';
    els.classBadge.style.backgroundPosition = `0% ${(playerClassRows[player.class_id || 'normal'] ?? 0) * 33.333}%`;
    els.className.textContent = `Class: ${currentClass?.name || player.class_id}`;
    renderElementChoices(player.element || 'earth');
    els.levelText.textContent = `LV ${player.level}`;
    els.atkText.textContent = `ATK ${player.atk}`;
    els.defText.textContent = `DEF ${player.def}`;
    els.specialStatsText.textContent = `ATK+${player.atk_stat || 0} AGI+${player.agi || 0} VIT+${player.vit || 0} LUK+${player.luk || 0} INT+${player.int_stat || 0}`;
    setBar(els.hpBar, player.hp, player.max_hp);
    setBar(els.mpBar, player.mp, player.max_mp);
    setBar(els.expBar, player.exp, nextExp || 1);
    els.hpText.textContent = `${player.hp}/${player.max_hp}`;
    els.mpText.textContent = `${player.mp}/${player.max_mp}`;
    els.expText.textContent = player.level >= 100 ? 'MAX' : `${player.exp}/${nextExp}`;

    renderEncounter(encounter, monsters);

    renderButtons(els.skillList, skills, state.selectedSkillId, (skill) => {
        state.selectedSkillId = skill.id;
        render();
    }, (skill) => {
        const cooldownText = skill.cooldown_remaining > 0 ? ` CD ${skill.cooldown_remaining}` : '';
        return `${skill.name} (${skill.damage}) MP ${skill.mana_cost}${cooldownText}`;
    }, (skill) => player.mp < skill.mana_cost || skill.cooldown_remaining > 0);

    renderCombatReadout(player, monsters, skills);
    renderWorldPanels(state.payload.world || {});
    renderPvpPanel(state.payload.pvp, skills);
    updateAutoFarmUi();
    state.scene?.setPlayerName(player.name);
    state.scene?.setPlayerClass(player.class_id || 'normal');
    state.scene?.setOnlinePlayers(state.payload.world?.onlinePlayers || []);

    els.classChoices.innerHTML = '';
    if (availableEvolutions.length === 0) {
        els.evolutionNote.textContent = player.level < 10
            ? 'ถึงเลเวล 10 เพื่อปลดล็อกการเปลี่ยนคลาส'
            : 'ยังไม่มีคลาสที่เปลี่ยนได้ในเลเวลนี้';
    } else {
        els.evolutionNote.textContent = 'เลือกเส้นทางใหม่ของตัวละคร';
        availableEvolutions.forEach((choice) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = choice.name;
            button.addEventListener('click', () => changeClass(choice.id));
            els.classChoices.appendChild(button);
        });
    }

    if (state.mode === 'pvp' && state.payload.pvp?.opponent) {
        state.scene?.setOpponent(state.payload.pvp.opponent);
    } else {
        state.scene?.setMonster(monsters.find((monster) => monster.id === state.selectedMonsterId) || monsters[0]);
    }
    renderClassEvolution(player, availableEvolutions, classEvolutionTree);
    els.modeRoot.dataset.mode = state.mode;
    state.scene?.setMode(state.mode);
}

function renderClassEvolution(player, availableEvolutions = [], tree = null) {
    els.classChoices.innerHTML = '';
    els.classEvolutionTree.innerHTML = '';

    if (availableEvolutions.length === 0) {
        els.evolutionNote.textContent = player.level < 10
            ? 'ถึงเลเวล 10 เพื่อปลดล็อกการเปลี่ยนคลาส'
            : 'ดูสายที่เลือกแล้ว และสายถัดไปที่ยังล็อกอยู่';
    } else {
        els.evolutionNote.textContent = 'เลือกได้ 1 สายเท่านั้น เมื่อเลือกแล้วสายอื่นจะถูกปิด';
    }

    const history = tree?.history || [];
    if (history.length > 0) {
        const historyLine = document.createElement('div');
        historyLine.className = 'evolution-history';
        history.forEach((item, index) => {
            const chip = document.createElement('span');
            chip.className = `evolution-chip ${item.status}`;
            chip.textContent = item.name;
            historyLine.appendChild(chip);

            if (index < history.length - 1) {
                const arrow = document.createElement('i');
                arrow.textContent = '>';
                historyLine.appendChild(arrow);
            }
        });
        els.classEvolutionTree.appendChild(historyLine);
    }

    const rows = tree?.rows || [];
    if (rows.length === 0) {
        const empty = document.createElement('p');
        empty.className = 'tree-empty';
        empty.textContent = 'ยังไม่มีสายถัดไปในข้อมูลเกม';
        els.classEvolutionTree.appendChild(empty);
        return;
    }

    rows.forEach((row) => {
        const group = document.createElement('div');
        group.className = 'evolution-row';

        const header = document.createElement('div');
        header.className = 'evolution-row-header';
        header.innerHTML = `<span>${row.from_class_name}</span><b>LV ${row.required_level}</b>`;
        group.appendChild(header);

        const choiceList = document.createElement('div');
        choiceList.className = 'evolution-choice-list';

        row.choices.forEach((choice) => {
            const node = document.createElement('button');
            node.type = 'button';
            node.className = `evolution-node ${choice.status}`;
            node.disabled = !choice.can_choose;
            node.innerHTML = `<strong>${choice.name}</strong><small>${choice.status_text}</small>`;
            if (choice.can_choose) {
                node.addEventListener('click', () => changeClass(choice.id));
            }
            choiceList.appendChild(node);
        });

        group.appendChild(choiceList);
        els.classEvolutionTree.appendChild(group);
    });
}

function renderEncounter(encounter, monsters) {
    els.monsterList.innerHTML = '';
    if (!encounter) {
        els.encounterLevelDice.textContent = '?';
        els.encounterLevelEffect.textContent = 'สำรวจก่อน';
        els.encounterCountDice.textContent = '?';
        els.encounterCountEffect.textContent = 'สำรวจก่อน';
        els.monsterNameText.textContent = 'ยังไม่พบมอนสเตอร์';
        els.monsterStatsText.textContent = 'กดสำรวจแผนที่เพื่อสุ่ม';
        els.monsterHpText.textContent = '0/0';
        setBar(els.monsterHpBar, 0, 1);
        return;
    }

    els.encounterLevelDice.textContent = encounter.levelDice;
    els.encounterLevelEffect.textContent = levelDiceLabel(encounter.levelDice, encounter.levelDelta, encounter.targetLevel);
    els.encounterCountDice.textContent = encounter.countDice;
    els.encounterCountEffect.textContent = encounter.isBoss ? 'บอส x1' : `ปกติ x${encounter.count}`;

    monsters.forEach((monster, index) => {
        const element = monsterElement(monster);
        const row = document.createElement('button');
        row.type = 'button';
        row.className = index === 0 ? 'selected' : '';
        const bossClass = monster.is_boss && monster.class_name ? ` (${monster.class_name})` : '';
        row.textContent = `${index + 1}. ${element.fullLabel} ${monster.name}${bossClass} LV ${monster.level}${monster.is_boss ? ' บอส' : ''}`;
        row.className = monster.id === state.selectedMonsterId ? 'selected' : '';
        row.disabled = monster.current_hp <= 0;
        row.textContent = `${index + 1}. ${element.fullLabel} ${monster.name}${bossClass} LV ${monster.level}${monster.is_boss ? ' บอส' : ''} HP ${monster.current_hp}/${monster.hp}`;
        row.style.borderColor = element.color;
        row.addEventListener('click', () => {
            state.selectedMonsterId = monster.id;
            render();
        });
        els.monsterList.appendChild(row);
    });
}

function levelDiceLabel(dice, delta, targetLevel) {
    if (dice === 6) return `ผู้เล่น +2 = LV ${targetLevel}`;
    if (dice === 5) return `ผู้เล่น +1 = LV ${targetLevel}`;
    if (dice === 4 || dice === 3) return `เท่า LV ${targetLevel}`;
    if (dice === 2) return `ผู้เล่น -1 = LV ${targetLevel}`;
    return `ผู้เล่น -2 = LV ${targetLevel}`;
}

function monsterElement(monster) {
    return elementMeta[monster?.element || 'neutral'] || elementMeta.neutral;
}

function elementAdvantage(attacker, defender) {
    const strongAgainst = {
        earth: 'water',
        wind: 'earth',
        fire: 'wind',
        water: 'fire',
    };

    if (!attacker || !defender || attacker === 'neutral' || defender === 'neutral') {
        return 1;
    }

    return strongAgainst[attacker] === defender ? 1.5 : 1;
}

function renderElementChoices(currentElement) {
    if (!els.elementChoices) return;

    els.elementChoices.innerHTML = '';
    ['earth', 'water', 'wind', 'fire'].forEach((element) => {
        const meta = elementMeta[element];
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `element-choice element-${element}${element === currentElement ? ' selected' : ''}`;
        button.textContent = meta.label;
        button.style.borderColor = meta.color;
        button.addEventListener('click', () => chooseElement(element));
        els.elementChoices.appendChild(button);
    });
}

function renderCombatReadout(player, monsters, skills) {
    const monster = monsters.find((item) => item.id === state.selectedMonsterId) || monsters[0];
    const skill = skills.find((item) => item.id === state.selectedSkillId) || skills[0];

    if (!monster) return;

    const visibleMonsterHp = state.lastMonsterId === monster.id && state.lastMonsterHp !== null
        ? state.lastMonsterHp
        : monster.current_hp;
    const canUseSkill = skill && player.mp >= skill.mana_cost;
    const cooldownReady = !skill || skill.cooldown_remaining <= 0;
    const activeSkill = canUseSkill && cooldownReady ? skill : null;
    const skillBonus = activeSkill?.id === 'punch' ? 1.1 : 1;
    const baseDamage = Math.max(1, Math.floor((player.atk + (activeSkill?.damage || 0) - monster.def) * skillBonus));
    const element = monsterElement(monster);
    const elementBonus = elementAdvantage(player.element || 'earth', monster.element || 'neutral');

    els.monsterNameText.textContent = `${element.fullLabel} ${monster.name} LV ${monster.level}`;
    els.monsterNameText.style.color = element.color;
    els.monsterStatsText.textContent = `ATK ${monster.atk} / DEF ${monster.def}`;
    els.monsterHpText.textContent = `${visibleMonsterHp}/${monster.hp}`;
    els.diceResultText.textContent = state.lastDice === null ? 'Roll: -' : `Roll: ${state.lastDice}`;
    els.hitEffectText.textContent = state.lastEffect === null ? 'Effect: -' : `Effect: ${state.lastEffect}`;
    els.monsterRollText.textContent = state.lastMonsterDice === null ? 'Monster Roll: -' : `Monster Roll: ${state.lastMonsterDice}`;
    els.monsterLastDamageText.textContent = state.lastMonsterDamage === null ? 'Monster DMG: -' : `Monster DMG: ${state.lastMonsterDamage}`;
    els.damageEstimateText.textContent = `Base DMG: ${baseDamage - 3}-${baseDamage + 6}${skillBonus > 1 ? ' (+10%)' : ''}${elementBonus > 1 ? ' x1.5 element' : ''}${skill && !canUseSkill ? ' (MP ไม่พอ)' : ''}${!cooldownReady ? ' (ติด cooldown)' : ''}`;
    els.lastDamageText.textContent = state.lastDamage === null ? 'Last DMG: -' : `Last DMG: ${state.lastDamage}`;
    renderDiceFace(els.playerDiceFace, els.playerDiceEffect, state.lastDice, state.lastEffect);
    renderDiceFace(els.monsterDiceFace, els.monsterDiceEffect, state.lastMonsterDice, state.lastMonsterEffect);
    setBar(els.monsterHpBar, visibleMonsterHp, monster.hp);
}

function renderDiceFace(faceEl, effectEl, dice, effect) {
    faceEl.textContent = dice ?? '?';
    effectEl.textContent = effect ?? '-';
    faceEl.parentElement.dataset.roll = dice ?? '';
}

function renderButtons(container, items, selectedId, onClick, label, disabled = () => false) {
    container.innerHTML = '';
    items.forEach((item) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = item.id === selectedId ? 'selected' : '';
        button.textContent = label(item);
        button.disabled = disabled(item);
        button.addEventListener('click', () => onClick(item));
        container.appendChild(button);
    });
}

function setBar(el, value, max) {
    el.style.width = `${Math.max(0, Math.min(100, (value / max) * 100))}%`;
}

function renderWorldPanels(world) {
    const onlinePlayers = world.onlinePlayers || [];
    const leaderboard = world.leaderboard || [];
    const botLeaderboard = world.botLeaderboard || [];
    const chatMessages = world.chatMessages || [];

    if (els.totalPlayersText) {
        els.totalPlayersText.textContent = `ผู้เล่นทั้งหมด ${world.totalPlayers ?? 0}`;
    }
    if (els.onlinePlayersText) {
        els.onlinePlayersText.textContent = `ออนไลน์ ${world.onlineCount ?? onlinePlayers.length}`;
    }

    if (els.arenaPlayerList) {
        els.arenaPlayerList.innerHTML = '';
        if (onlinePlayers.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'empty-note';
            empty.textContent = 'ยังไม่มีผู้เล่นอื่นออนไลน์';
            els.arenaPlayerList.appendChild(empty);
        }
        onlinePlayers.forEach((player) => {
            const element = elementMeta[player.element || 'earth'] || elementMeta.earth;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = player.id === state.selectedOpponentId ? 'selected' : '';
            button.textContent = `${element.fullLabel} ${player.name} LV ${player.level} | PVP ${player.pvp_rating}${player.pvp_queue_at ? ' | รอประลอง' : ''}`;
            button.style.borderColor = element.color;
            button.addEventListener('click', () => {
                state.selectedOpponentId = player.id;
                renderPvpPanel(state.payload.pvp, state.payload.skills || []);
            });
            els.arenaPlayerList.appendChild(button);
        });
    }

    if (els.leaderboardList) {
        els.leaderboardList.innerHTML = '';
        leaderboard.forEach((player, index) => {
            const row = document.createElement('p');
            row.textContent = `#${index + 1} ${player.name} | ${player.pvp_rating} | W ${player.pvp_wins} / L ${player.pvp_losses}`;
            els.leaderboardList.appendChild(row);
        });
    }

    if (els.botLeaderboardList) {
        els.botLeaderboardList.innerHTML = '';
        botLeaderboard.forEach((player, index) => {
            const row = document.createElement('p');
            row.textContent = `#${index + 1} ${player.name} | ${player.bot_rating} | W ${player.bot_wins} / L ${player.bot_losses}`;
            els.botLeaderboardList.appendChild(row);
        });
    }

    if (els.chatList) {
        els.chatList.innerHTML = '';
        chatMessages.forEach((message) => {
            const row = document.createElement('p');
            row.innerHTML = `<strong>${escapeHtml(message.player_name)}</strong>: ${escapeHtml(message.message)}`;
            els.chatList.appendChild(row);
        });
        els.chatList.scrollTop = els.chatList.scrollHeight;
    }
}

function rememberWorldState(world, notify = true) {
    const onlinePlayers = world.onlinePlayers || [];
    const chatMessages = world.chatMessages || [];
    const nextOnlineIds = new Set(onlinePlayers.map((player) => player.id));

    if (notify && state.knownOnlineIds.size > 0) {
        onlinePlayers.forEach((player) => {
            if (!state.knownOnlineIds.has(player.id)) {
                notifyWorld(`${player.name} ออนไลน์แล้ว`);
            }
        });
    }
    state.knownOnlineIds = nextOnlineIds;

    chatMessages.forEach((message) => {
        if (state.seenChatIds.has(message.id)) return;
        if (notify) {
            const isSelf = message.player_id === state.payload?.player?.id;
            state.scene?.showSpeech(`${message.player_name}: ${message.message}`, isSelf ? 'player' : 'monster', message.player_id);
        }
        state.seenChatIds.add(message.id);
    });
}

function notifyWorld(message) {
    log(message);
    state.scene?.showSpeech(message, 'monster');
    playSfx('chat');
}

function renderPvpPanel(pvp, skills) {
    const opponent = pvp?.opponent || (state.payload.world?.onlinePlayers || []).find((player) => player.id === state.selectedOpponentId);
    const queue = state.payload.pvpQueue || {};
    const hp = opponent?.current_hp ?? opponent?.hp ?? 0;
    const maxHp = opponent?.hp ?? opponent?.max_hp ?? 1;

    if (els.pvpOpponentName) {
        const element = elementMeta[opponent?.element || 'earth'] || elementMeta.earth;
        els.pvpOpponentName.textContent = pvp && opponent
            ? `${element.fullLabel} ${opponent.name} LV ${opponent.level}`
            : (queue.waiting ? 'กำลังรอผู้เล่นคนที่ 2' : 'กดเข้าลานเพื่อจับคู่');
        els.pvpOpponentName.style.color = opponent ? element.color : '';
        els.pvpOpponentStats.textContent = opponent
            ? `ATK ${opponent.atk ?? '-'} / DEF ${opponent.def ?? '-'} / Rating ${opponent.pvp_rating ?? '-'}`
            : (queue.waiting ? 'ต้องมีอีกคนกดเข้าลานประลองในช่วงเวลาเดียวกัน' : 'ต้องมีผู้เล่นออนไลน์และกดเข้าลานพร้อมกัน');
        els.pvpOpponentHpText.textContent = opponent ? `${hp}/${maxHp}` : '0/0';
        setBar(els.pvpOpponentHpBar, hp, maxHp);
    }

    if (els.pvpFightButton) {
        els.pvpFightButton.disabled = !pvp || state.pvpResolved || (skills || []).length === 0;
    }
}

function setMode(mode) {
    state.mode = mode;
    els.modeRoot.dataset.mode = mode;
    els.monsterModeButton?.classList.toggle('selected', mode !== 'pvp');
    els.arenaModeButton?.classList.toggle('selected', mode === 'pvp' && !state.payload?.pvp?.is_bot);
    els.botPvpButton?.classList.toggle('selected', mode === 'pvp' && Boolean(state.payload?.pvp?.is_bot));
    state.scene?.setMode(mode);
    if (state.payload) {
        render();
    }
}

function wait(ms) {
    return new Promise((resolve) => window.setTimeout(resolve, ms));
}

function autoFarmLabel(type = state.autoFarm.type) {
    return type === 'bot' ? 'บอท' : 'มอน';
}

function readAutoTargetRounds() {
    const rounds = Number.parseInt(els.autoRoundInput?.value || '1', 10);
    return Math.max(1, Math.min(99, Number.isNaN(rounds) ? 1 : rounds));
}

function autoHpThresholdPercent(player = state.payload?.player) {
    const vit = Number(player?.vit || 0);
    return Math.max(8, 35 - (vit * 2));
}

function playerHpPercent(player = state.payload?.player) {
    const maxHp = Math.max(1, Number(player?.max_hp || 1));
    return (Number(player?.hp || 0) / maxHp) * 100;
}

function canContinueAutoFarm() {
    const player = state.payload?.player;
    if (!player) return false;

    if (Number(player.hp || 0) <= 0) {
        setMode('world');
        stopAutoFarm('HP หมด กลับหน้าโลกแล้ว กดพักฟื้นเพื่อฟาร์มต่อ');
        return false;
    }

    const threshold = autoHpThresholdPercent(player);
    if (playerHpPercent(player) <= threshold) {
        setMode('world');
        stopAutoFarm(`HP ต่ำกว่า ${threshold}% ตามค่า VIT (${player.vit || 0}) กดพักฟื้นก่อนฟาร์มต่อ`);
        return false;
    }

    return true;
}

function updateAutoFarmUi() {
    const auto = state.autoFarm;
    const active = auto.active;
    if (els.autoStopButton) {
        els.autoStopButton.disabled = !active;
    }
    if (els.autoMonsterButton) {
        els.autoMonsterButton.disabled = active;
    }
    if (els.autoBotButton) {
        els.autoBotButton.disabled = active;
    }
    if (els.autoContinueOnceButton) {
        els.autoContinueOnceButton.disabled = active;
    }
    if (els.autoRoundInput) {
        els.autoRoundInput.disabled = active;
    }
    if (els.autoFarmStatus) {
        els.autoFarmStatus.textContent = active
            ? `Auto ${autoFarmLabel()} ${auto.completedRounds}/${auto.targetRounds}`
            : 'พร้อมฟาร์ม';
    }
}

function clearAutoTimer() {
    if (state.autoFarm.timer) {
        window.clearTimeout(state.autoFarm.timer);
        state.autoFarm.timer = null;
    }
}

function startAutoFarm(type, forcedRounds = null) {
    if (state.autoFarm.active) return;

    state.autoFarm = {
        active: true,
        type,
        targetRounds: forcedRounds ?? readAutoTargetRounds(),
        completedRounds: 0,
        running: false,
        stopRequested: false,
        timer: null,
    };
    updateAutoFarmUi();
    log(`เริ่ม Auto ${autoFarmLabel(type)} ${state.autoFarm.targetRounds} รอบ`);
    scheduleAutoStep(250);
}

function stopAutoFarm(reason = 'หยุด Auto แล้ว') {
    clearAutoTimer();
    const wasActive = state.autoFarm.active;
    state.autoFarm.active = false;
    state.autoFarm.running = false;
    state.autoFarm.stopRequested = true;
    updateAutoFarmUi();
    if (wasActive) {
        log(reason);
    }
}

function completeAutoRound(type) {
    if (!state.autoFarm.active || state.autoFarm.type !== type) return;

    state.autoFarm.completedRounds += 1;
    updateAutoFarmUi();
    log(`Auto ${autoFarmLabel(type)} จบรอบ ${state.autoFarm.completedRounds}/${state.autoFarm.targetRounds}`);

    if (state.autoFarm.stopRequested || state.autoFarm.completedRounds >= state.autoFarm.targetRounds) {
        stopAutoFarm(`Auto ${autoFarmLabel(type)} ครบ ${state.autoFarm.completedRounds} รอบแล้ว`);
        return;
    }

    scheduleAutoStep(1000);
}

function scheduleAutoStep(delay = 750) {
    clearAutoTimer();
    if (!state.autoFarm.active || state.autoFarm.stopRequested) return;
    state.autoFarm.timer = window.setTimeout(runAutoStep, delay);
}

async function runAutoStep() {
    if (!state.autoFarm.active || state.autoFarm.running || state.autoFarm.stopRequested) return;

    state.autoFarm.running = true;
    try {
        if (state.autoFarm.type === 'bot') {
            await runAutoBotStep();
        } else {
            await runAutoMonsterStep();
        }
    } catch (error) {
        stopAutoFarm(`Auto หยุด: ${error.message}`);
    } finally {
        state.autoFarm.running = false;
    }
}

async function runAutoMonsterStep() {
    await ensurePayload();
    if (!canContinueAutoFarm()) return;

    const livingMonsters = state.payload.encounter?.monsters?.filter((monster) => monster.current_hp > 0) || [];
    if (livingMonsters.length === 0 || state.mode !== 'battle') {
        await rollEncounter();
        await wait(450);
    }

    const targets = state.payload.encounter?.monsters?.filter((monster) => monster.current_hp > 0) || [];
    if (targets.length === 0) {
        completeAutoRound('monster');
        return;
    }

    state.selectedMonsterId = targets[0].id;
    render();
    await fight();
}

async function runAutoBotStep() {
    await ensurePayload();
    if (!canContinueAutoFarm()) return;

    if (!state.payload.pvp || !state.payload.pvp.is_bot || state.pvpResolved) {
        await startBotPvp();
        await wait(450);
    }

    if (!state.payload.pvp || !state.payload.pvp.is_bot) {
        throw new Error('เริ่มบอท PVP ไม่สำเร็จ');
    }

    await fightPvp();
}

async function startPvp() {
    await ensurePayload();

    const { player } = state.payload;
    try {
        const payload = await request(`/game/player/${player.id}/pvp/start`, {
            method: 'POST',
            body: JSON.stringify({}),
        });
        resetBattleReadout();
        state.pvpResolved = false;
        setPayload(payload);
        if (payload.pvp) {
            setMode('pvp');
            log(`เข้าสู่ลานประลองกับ ${payload.pvp.opponent.name}`);
        } else {
            log('เข้าคิวลานประลองแล้ว รอผู้เล่นอีกคนกดเข้ามาพร้อมกัน');
        }
    } catch (error) {
        log(error.message);
    }
}

async function startBotPvp() {
    await ensurePayload();

    const { player } = state.payload;
    try {
        const payload = await request(`/game/player/${player.id}/pvp/bot/start`, {
            method: 'POST',
            body: JSON.stringify({}),
        });
        resetBattleReadout();
        state.pvpResolved = false;
        setPayload(payload);
        setMode('pvp');
        log(`เริ่มสู้กับบอท: ${payload.pvp.opponent.element_label} ${payload.pvp.opponent.name} LV ${payload.pvp.opponent.level}`);
    } catch (error) {
        log(error.message);
    }
}

async function fightPvp() {
    await ensurePayload();
    const { player } = state.payload;
    if (!state.payload.pvp) return;

    els.pvpFightButton.disabled = true;
    try {
        const payload = await request(`/game/player/${player.id}/pvp/fight`, {
            method: 'POST',
            body: JSON.stringify({ skill_id: state.selectedSkillId }),
        });
        setPayload(payload);
        showPvpBattle(payload.pvpBattle);
    } catch (error) {
        log(error.message);
    } finally {
        els.pvpFightButton.disabled = state.pvpResolved || !state.payload?.pvp;
    }
}

function showPvpBattle(battle) {
    state.pvpResolved = battle.won || battle.lost;

    battle.turns.forEach((turn, index) => {
        window.setTimeout(() => {
            if (!turn.actor) {
                log(turn.text);
                return;
            }
            const actor = turn.actor === 'player' ? 'player' : 'monster';
            state.scene?.playHit(actor, turn.damage, turn.effect);
            playSfx(turn.effect === 'x2' || turn.effect === 'critical' ? 'crit' : 'hit');
            log(turn.text);
        }, index * 180);
    });

    if (battle.won || battle.lost) {
        log(`${battle.isBot ? 'บอท PVP' : 'PVP'}: ${battle.won ? 'ชนะ' : 'แพ้'} | Rating ${battle.ratingChange > 0 ? '+' : ''}${battle.ratingChange}`);
        playSfx(battle.won ? 'victory' : 'hit');
        window.setTimeout(() => {
            state.scene?.playDeathPose(battle.won ? 'monster' : 'player');
        }, Math.max(520, battle.turns.length * 180));
        window.setTimeout(async () => {
            await refreshWorld();
            if (battle.isBot && battle.lost) {
                setMode('world');
                if (state.autoFarm.active && state.autoFarm.type === 'bot') {
                    stopAutoFarm('HP หมดจากบอท PVP กลับหน้าโลกแล้ว กดพักฟื้นเพื่อฟาร์มต่อ');
                }
                return;
            }
            if (battle.isBot && state.autoFarm.active && state.autoFarm.type === 'bot') {
                completeAutoRound('bot');
            } else if (battle.isBot && els.autoFarmStatus) {
                els.autoFarmStatus.textContent = 'จบบอทแล้ว: กด Auto บอท เพื่อสู้ต่อ หรือกลับหน้าโลก';
            }
        }, 800);
    } else if (battle.isBot && state.autoFarm.active && state.autoFarm.type === 'bot') {
        scheduleAutoStep(900);
    }
}

async function refreshWorld() {
    if (!state.payload?.player) return;

    try {
        const hadPvp = Boolean(state.payload.pvp);
        const payload = await request('/game/bootstrap', {
            method: 'POST',
            body: JSON.stringify({ browserToken }),
        });
        setPayload(payload);
        rememberWorldState(payload.world || {}, true);
        if (!hadPvp && payload.pvp) {
            setMode('pvp');
            log(`จับคู่ลานประลองแล้ว: ${payload.pvp.opponent.name}`);
        }
    } catch (error) {
        log(error.message);
    }
}

function startWorldPolling() {
    if (state.worldTimer) return;
    state.worldTimer = window.setInterval(refreshWorld, 5000);
}

async function sendChat() {
    await ensurePayload();
    const message = els.chatInput.value.trim();
    if (!message) return;

    const { player } = state.payload;
    try {
        const world = await request(`/game/player/${player.id}/chat`, {
            method: 'POST',
            body: JSON.stringify({ message }),
        });
        els.chatInput.value = '';
        state.payload.world = world;
        rememberWorldState(world, true);
        renderWorldPanels(world);
        playSfx('chat');
    } catch (error) {
        log(error.message);
    }
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

async function fight() {
    await ensurePayload();

    const { player } = state.payload;
    if (!state.selectedMonsterId) return;
    els.fightButton.disabled = true;
    try {
        playSfx('click');
        const payload = await request(`/game/player/${player.id}/fight`, {
            method: 'POST',
            body: JSON.stringify({
                skill_id: state.selectedSkillId,
                target_monster_id: state.selectedMonsterId,
            }),
        });
        setPayload(payload);
        showBattle(payload.battle);
    } catch (error) {
        log(error.message);
    } finally {
        els.fightButton.disabled = state.encounterResolved || !(state.payload?.encounter?.monsters?.some((monster) => monster.current_hp > 0));
    }
}

async function rollEncounter() {
    await ensurePayload();

    const { player } = state.payload;
    els.rollEncounterButton.disabled = true;
    try {
        playSfx('explore');
        const payload = await request(`/game/player/${player.id}/roll-encounter`, {
            method: 'POST',
            body: JSON.stringify({}),
        });
        resetBattleReadout();
        state.encounterResolved = false;
        state.mode = 'battle';
        setPayload(payload);
        log(`สำรวจพบ: ${payload.encounter.isBoss ? 'บอส 1 ตัว' : `มอนสเตอร์ ${payload.encounter.count} ตัว`} ระดับ LV ${payload.encounter.targetLevel}`);
    } catch (error) {
        log(error.message);
    } finally {
        els.rollEncounterButton.disabled = false;
    }
}

async function walkWorld(dx, dy) {
    await ensurePayload();

    if (state.walking || state.mode !== 'world') {
        return;
    }

    state.walking = true;
    try {
        playSfx('click');
        state.scene?.moveWorldPlayer(dx, dy);

        if (Math.random() < 0.28) {
            log('เดินสำรวจแล้วเจอมอนสเตอร์!');
            await rollEncounter();
        } else {
            log('เดินสำรวจแผนที่... ยังปลอดภัย');
        }
    } catch (error) {
        log(error.message);
    } finally {
        state.walking = false;
    }
}

function resetBattleReadout() {
    state.lastDamage = null;
    state.lastDice = null;
    state.lastEffect = null;
    state.lastMonsterDamage = null;
    state.lastMonsterDice = null;
    state.lastMonsterEffect = null;
    state.lastMonsterHp = null;
    state.lastMonsterId = null;
}

function showBattle(battle) {
    if (!state.payload.encounter) {
        state.payload.encounter = battle.encounter;
    }
    state.encounterResolved = battle.won || battle.playerDefeated;

    const playerHits = battle.turns.filter((turn) => turn.actor === 'player');
    const monsterHits = battle.turns.filter((turn) => turn.actor === 'monster');
    const lastPlayerHit = playerHits.at(-1);
    const lastMonsterHit = monsterHits.at(-1);
    if (lastPlayerHit) {
        state.lastDamage = lastPlayerHit.damage;
        state.lastDice = lastPlayerHit.dice;
        state.lastEffect = lastPlayerHit.label;
        state.lastMonsterHp = lastPlayerHit.monsterHp;
        state.lastMonsterId = lastPlayerHit.targetIndex !== undefined
            ? battle.encounter.monsters[lastPlayerHit.targetIndex]?.id
            : battle.monster.id;
    }
    if (lastMonsterHit) {
        state.lastMonsterDamage = lastMonsterHit.damage;
        state.lastMonsterDice = lastMonsterHit.dice;
        state.lastMonsterEffect = lastMonsterHit.label;
    } else {
        state.lastMonsterDamage = null;
        state.lastMonsterDice = null;
        state.lastMonsterEffect = null;
    }
    render();

    log(`${battle.won ? 'ชนะ' : 'รอดกลับมาได้'}: ได้รับ EXP ${battle.expGained}`);
    if (battle.bossReward) {
        log(`รางวัลบอส: ${bossStatLabel(battle.bossReward.stat)} +${battle.bossReward.amount}`);
    }
    if (battle.levelSummary.leveledUp) {
        log(`Level Up! ตอนนี้เลเวล ${battle.levelSummary.currentLevel}`);
    }
    battle.turns.slice(-4).forEach((turn, index) => {
        window.setTimeout(() => {
            if (!turn.actor) {
                log(turn.text);
                return;
            }
            state.scene?.playHit(turn.actor, turn.damage, turn.effect);
            playSfx(turn.effect === 'x2' || turn.effect === 'critical' ? 'crit' : 'hit');
            log(turn.text);
        }, index * 180);
    });

    if (battle.turns.some((turn) => turn.type === 'monster_defeated')) {
        window.setTimeout(() => {
            const defeated = battle.turns.filter((turn) => turn.type === 'monster_defeated').at(-1);
            state.scene?.playMonsterDeath(defeated?.monster?.name || battle.monster.name);
        }, 820);
    }
    if (battle.playerDefeated) {
        window.setTimeout(() => {
            state.scene?.playDeathPose('player');
        }, Math.max(520, battle.turns.length * 180));
    }

    if (!battle.won && !battle.playerDefeated) {
        if (state.autoFarm.active && state.autoFarm.type === 'monster') {
            scheduleAutoStep(900);
        }
        return;
    }

    window.setTimeout(() => {
        state.mode = 'world';
        render();
        if (battle.won) playSfx('victory');
        log('กลับสู่แผนที่โลกแล้ว กดสำรวจแผนที่เพื่อไปต่อ');
        handleMonsterRoundFinished(battle);
    }, battle.won ? 1900 : 900);
}

function handleMonsterRoundFinished(battle) {
    if (state.autoFarm.active && state.autoFarm.type === 'monster') {
        if (battle.playerDefeated) {
            stopAutoFarm('Auto มอนหยุด เพราะ HP หมด');
        } else {
            completeAutoRound('monster');
        }
    } else if (els.autoFarmStatus) {
        els.autoFarmStatus.textContent = 'จบรอบมอนแล้ว: กด Auto มอน เพื่อสู้ต่อ หรืออยู่หน้าโลก';
    }
}

function bossStatLabel(stat) {
    return {
        atk_stat: 'ATK',
        agi: 'AGI',
        vit: 'VIT',
        luk: 'LUK',
        int_stat: 'INT',
    }[stat] || stat;
}

async function changeClass(classId) {
    const { player } = state.payload;
    try {
        const payload = await request(`/game/player/${player.id}/change-class`, {
            method: 'POST',
            body: JSON.stringify({ class_id: classId }),
        });
        setPayload(payload);
        log(`เปลี่ยนคลาสเป็น ${payload.class.name} สำเร็จ`);
    } catch (error) {
        log(error.message);
    }
}

async function chooseElement(element) {
    await ensurePayload();
    const { player } = state.payload;

    try {
        const payload = await request(`/game/player/${player.id}/element`, {
            method: 'PATCH',
            body: JSON.stringify({ element }),
        });
        setPayload(payload);
        log(`เลือก${elementMeta[element]?.fullLabel || element}แล้ว`);
    } catch (error) {
        log(error.message);
    }
}

async function rename() {
    const { player } = state.payload;
    const name = els.playerName.value.trim() || 'นักผจญภัย';
    const payload = await request(`/game/player/${player.id}/rename`, {
        method: 'PATCH',
        body: JSON.stringify({ name }),
    });
    setPayload(payload);
    log(`บันทึกชื่อ ${name} แล้ว`);
}

async function recoverPlayer() {
    await ensurePayload();
    const { player } = state.payload;

    try {
        const payload = await request(`/game/player/${player.id}/recover`, {
            method: 'POST',
            body: JSON.stringify({}),
        });
        setPayload(payload);
        setMode('world');
        log('พักฟื้นสำเร็จ: HP และ MP เต็มแล้ว');
        if (els.autoFarmStatus) {
            els.autoFarmStatus.textContent = 'พร้อมฟาร์มต่อ';
        }
    } catch (error) {
        log(error.message);
    }
}

function log(message) {
    const row = document.createElement('p');
    row.textContent = message;
    els.battleLog.prepend(row);
    while (els.battleLog.children.length > 6) {
        els.battleLog.lastElementChild.remove();
    }
}

function updateMenuToggleLabel() {
    const hidden = document.body.classList.contains('menu-hidden');
    els.toggleMenuButton.textContent = hidden ? 'คำสั่ง: แสดง' : 'คำสั่ง: ซ่อน';
    els.toggleMenuButton.classList.toggle('selected', hidden);
}

els.renameButton.addEventListener('click', rename);
els.toggleStatusButton.addEventListener('click', () => {
    playSfx('click');
    document.body.classList.toggle('status-hidden');
});
els.toggleMenuButton.addEventListener('click', () => {
    playSfx('click');
    document.body.classList.toggle('menu-hidden');
    updateMenuToggleLabel();
});
els.monsterModeButton?.addEventListener('click', () => {
    playSfx('click');
    if (state.autoFarm.active) stopAutoFarm('หยุด Auto แล้วกลับหน้าโลก');
    setMode('world');
});
els.arenaModeButton?.addEventListener('click', () => {
    playSfx('click');
    if (state.autoFarm.active) stopAutoFarm('หยุด Auto แล้วเข้าลานประลอง');
    startPvp();
});
els.botPvpButton?.addEventListener('click', () => {
    playSfx('click');
    if (state.autoFarm.active) stopAutoFarm('หยุด Auto เดิมแล้วเริ่มบอทใหม่');
    startBotPvp();
});
els.autoMonsterButton?.addEventListener('click', () => {
    playSfx('click');
    startAutoFarm('monster');
});
els.autoBotButton?.addEventListener('click', () => {
    playSfx('click');
    startAutoFarm('bot');
});
els.autoContinueOnceButton?.addEventListener('click', () => {
    playSfx('click');
    const type = state.payload?.pvp?.is_bot || state.mode === 'pvp' ? 'bot' : 'monster';
    startAutoFarm(type, 1);
});
els.autoReturnButton?.addEventListener('click', () => {
    playSfx('click');
    if (state.autoFarm.active) stopAutoFarm('หยุด Auto แล้วกลับหน้าโลก');
    setMode('world');
    refreshWorld();
});
els.recoverButton?.addEventListener('click', () => {
    playSfx('click');
    if (state.autoFarm.active) stopAutoFarm('หยุด Auto เพื่อพักฟื้น');
    recoverPlayer();
});
els.autoStopButton?.addEventListener('click', () => {
    playSfx('click');
    stopAutoFarm('หยุด Auto ทันที');
});
els.mapMoveButtons?.forEach((button) => {
    button.addEventListener('click', () => {
        const move = button.dataset.move;
        const vector = {
            up: [0, -1],
            down: [0, 1],
            left: [-1, 0],
            right: [1, 0],
        }[move] || [0, 0];
        walkWorld(vector[0], vector[1]);
    });
});
els.pvpFightButton?.addEventListener('click', fightPvp);
els.chatSendButton?.addEventListener('click', sendChat);
els.chatInput?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
        sendChat();
    }
});
document.addEventListener('keydown', (event) => {
    if (event.target && ['INPUT', 'TEXTAREA'].includes(event.target.tagName)) {
        return;
    }

    if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) {
        event.preventDefault();
        const vector = {
            ArrowLeft: [-1, 0],
            ArrowRight: [1, 0],
            ArrowUp: [0, -1],
            ArrowDown: [0, 1],
        }[event.key];
        walkWorld(vector[0], vector[1]);
    }
});
els.soundToggleButton?.addEventListener('click', () => setSfxEnabled(!audio.sfxEnabled));
els.bgmToggleButton?.addEventListener('click', () => setBgmEnabled(!audio.bgmEnabled));
document.addEventListener('pointerdown', ensureAudio, { once: true });
setSfxEnabled(audio.sfxEnabled);
setBgmEnabled(audio.bgmEnabled);
updateMenuToggleLabel();
window.rollEncounterAction = rollEncounter;
window.fightAction = fight;
if (isAuthenticated) {
    bootstrap().catch((error) => log(error.message));
} else if (els.saveStatus) {
    els.saveStatus.textContent = 'Login required';
}
