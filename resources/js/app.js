import './bootstrap';
import Phaser from 'phaser';

const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const tokenKey = 'dragon-path-player-token';
const browserToken = localStorage.getItem(tokenKey) || crypto.randomUUID();
localStorage.setItem(tokenKey, browserToken);

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
};

const els = {
    saveStatus: document.getElementById('save-status'),
    toggleStatusButton: document.getElementById('toggle-status-button'),
    toggleMenuButton: document.getElementById('toggle-menu-button'),
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
    classChoices: document.getElementById('class-choices'),
};

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
        this.titleText = this.add.text(28, 24, 'แผนที่โลก', { fontFamily: 'Arial', fontSize: '18px', color: '#184a35' });
        this.player = this.add.sprite(230, 310, 'playerTex').setScale(2.2);
        this.monster = this.add.sprite(570, 300, 'monster_default').setScale(2.4);
        this.monsterName = this.add.text(500, 385, 'Monster', { fontFamily: 'Arial', fontSize: '18px', color: '#ffffff' });
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
        this.monster.setTexture(this.textures.exists(textureKey) ? textureKey : 'monster_default');
        this.monster.setAlpha(1).setAngle(0).setScale(2.4).setY(300);
        this.monsterName.setText(`${monster.name} LV ${monster.level}`);
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
            fontFamily: 'Arial',
            fontSize: '28px',
            fontStyle: 'bold',
            color: '#ffd166',
            stroke: '#10131c',
            strokeThickness: 5,
        }).setOrigin(0.5);

        this.tweens.add({
            targets: this.monster,
            alpha: 0,
            angle: 18,
            y: this.monster.y + 40,
            scale: 1.6,
            duration: 620,
            ease: 'Cubic.easeIn',
        });

        this.tweens.add({
            targets: deathText,
            y: deathText.y - 34,
            alpha: 0,
            duration: 1200,
            onComplete: () => deathText.destroy(),
        });
    }

    playHit(actor, amount = null, effect = null) {
        const target = actor === 'player' ? this.monster : this.player;
        this.tweens.add({
            targets: target,
            x: target.x + (actor === 'player' ? 18 : -18),
            duration: 80,
            yoyo: true,
            repeat: 2,
        });

        if (amount !== null) {
            const isPlayerHit = actor === 'player';
            const color = this.effectColor(effect, isPlayerHit);
            const x = actor === 'player' ? this.monster.x : this.player.x;
            const y = actor === 'player' ? this.monster.y - 86 : this.player.y - 86;
            const effectText = this.effectText(effect);
            const floating = this.add.text(x, y, `${amount > 0 ? `-${amount}` : 'MISS'}${effectText}`, {
                fontFamily: 'Arial',
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
}

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

async function request(url, options = {}) {
    els.saveStatus.textContent = 'Saving';
    const response = await fetch(url, {
        ...options,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            Accept: 'application/json',
            ...(options.headers || {}),
        },
    });

    const data = await response.json();
    if (!response.ok) {
        els.saveStatus.textContent = 'Error';
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
    render();

    if (battle.won || battle.playerDefeated) {
}

function render() {
    const { player, class: currentClass, nextExp, encounter, skills, availableEvolutions } = state.payload;
    const monsters = encounter?.monsters || [];
    const livingMonsters = monsters.filter((monster) => monster.current_hp > 0);
    els.fightButton.disabled = livingMonsters.length === 0 || state.encounterResolved;
    els.playerName.value = player.name;
    els.classBadge.textContent = (currentClass?.name || 'คน').slice(0, 2);
    els.className.textContent = `Class: ${currentClass?.name || player.class_id}`;
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

    state.scene?.setMonster(monsters.find((monster) => monster.id === state.selectedMonsterId) || monsters[0]);
    els.modeRoot.dataset.mode = state.mode;
    state.scene?.setMode(state.mode);
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
        const row = document.createElement('button');
        row.type = 'button';
        row.className = index === 0 ? 'selected' : '';
        row.textContent = `${index + 1}. ${monster.name} LV ${monster.level}${monster.is_boss ? ' บอส' : ''}`;
        row.className = monster.id === state.selectedMonsterId ? 'selected' : '';
        row.disabled = monster.current_hp <= 0;
        row.textContent = `${index + 1}. ${monster.name} LV ${monster.level}${monster.is_boss ? ' บอส' : ''} HP ${monster.current_hp}/${monster.hp}`;
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

    els.monsterNameText.textContent = `${monster.name} LV ${monster.level}`;
    els.monsterStatsText.textContent = `ATK ${monster.atk} / DEF ${monster.def}`;
    els.monsterHpText.textContent = `${visibleMonsterHp}/${monster.hp}`;
    els.diceResultText.textContent = state.lastDice === null ? 'Roll: -' : `Roll: ${state.lastDice}`;
    els.hitEffectText.textContent = state.lastEffect === null ? 'Effect: -' : `Effect: ${state.lastEffect}`;
    els.monsterRollText.textContent = state.lastMonsterDice === null ? 'Monster Roll: -' : `Monster Roll: ${state.lastMonsterDice}`;
    els.monsterLastDamageText.textContent = state.lastMonsterDamage === null ? 'Monster DMG: -' : `Monster DMG: ${state.lastMonsterDamage}`;
    els.damageEstimateText.textContent = `Base DMG: ${baseDamage - 3}-${baseDamage + 6}${skillBonus > 1 ? ' (+10%)' : ''}${skill && !canUseSkill ? ' (MP ไม่พอ)' : ''}`;
    els.damageEstimateText.textContent = `Base DMG: ${baseDamage - 3}-${baseDamage + 6}${skillBonus > 1 ? ' (+10%)' : ''}${skill && !canUseSkill ? ' (MP ไม่พอ)' : ''}${!cooldownReady ? ' (ติด cooldown)' : ''}`;
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

async function fight() {
    await ensurePayload();

    const { player } = state.payload;
    if (!state.selectedMonsterId) return;
    els.fightButton.disabled = true;
    try {
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
    }
    battle.turns.slice(-4).forEach((turn, index) => {
        window.setTimeout(() => {
            state.scene?.playHit(turn.actor, turn.damage, turn.effect);
            log(turn.text);
        }, index * 180);
    });

    if (battle.turns.some((turn) => turn.type === 'monster_defeated')) {
        window.setTimeout(() => {
            const defeated = battle.turns.filter((turn) => turn.type === 'monster_defeated').at(-1);
            state.scene?.playMonsterDeath(defeated?.monster?.name || battle.monster.name);
        }, 820);
    }

    if (!battle.won && !battle.playerDefeated) {
        return;
    }

    window.setTimeout(() => {
        state.mode = 'world';
        render();
        log('กลับสู่แผนที่โลกแล้ว กดสำรวจแผนที่เพื่อไปต่อ');
    }, battle.won ? 1900 : 900);
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

function log(message) {
    const row = document.createElement('p');
    row.textContent = message;
    els.battleLog.prepend(row);
    while (els.battleLog.children.length > 6) {
        els.battleLog.lastElementChild.remove();
    }
}

els.renameButton.addEventListener('click', rename);
els.toggleStatusButton.addEventListener('click', () => {
    document.body.classList.toggle('status-hidden');
});
els.toggleMenuButton.addEventListener('click', () => {
    document.body.classList.toggle('menu-hidden');
});
window.rollEncounterAction = rollEncounter;
window.fightAction = fight;
bootstrap().catch((error) => log(error.message));
