<?php

namespace Database\Seeders;

use App\Models\CharacterClass;
use App\Models\ClassEvolution;
use App\Models\Monster;
use App\Models\Skill;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GameSeedSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $classes = [
            ['id' => 'normal', 'name' => 'คนปกติ', 'milestone_level' => 1, 'hp_bonus' => 0, 'mp_bonus' => 0, 'atk_bonus' => 0, 'def_bonus' => 0, 'ability_name' => 'ใจสู้', 'ability_description' => 'เริ่มต้นด้วยค่าสถานะพื้นฐาน'],
            ['id' => 'cavalry', 'name' => 'ทหารขี่ม้า', 'milestone_level' => 10, 'hp_bonus' => 35, 'mp_bonus' => 0, 'atk_bonus' => 12, 'def_bonus' => 8, 'ability_name' => 'พุ่งชน', 'ability_description' => 'โจมตีแรงและถึกขึ้น'],
            ['id' => 'mage', 'name' => 'นักเวท', 'milestone_level' => 10, 'hp_bonus' => 10, 'mp_bonus' => 45, 'atk_bonus' => 18, 'def_bonus' => 2, 'ability_name' => 'พลังเวท', 'ability_description' => 'สกิลเวททำความเสียหายสูง'],
            ['id' => 'archer', 'name' => 'นักธนู', 'milestone_level' => 10, 'hp_bonus' => 20, 'mp_bonus' => 15, 'atk_bonus' => 15, 'def_bonus' => 4, 'ability_name' => 'เล็งจุดอ่อน', 'ability_description' => 'โจมตีสม่ำเสมอและคริติคอลง่าย'],
            ['id' => 'dragon_knight', 'name' => 'ไนท์ขี่มังกร', 'milestone_level' => 20, 'hp_bonus' => 80, 'mp_bonus' => 20, 'atk_bonus' => 35, 'def_bonus' => 25, 'ability_name' => 'เกราะมังกร', 'ability_description' => 'พลังโจมตีและป้องกันเพิ่มมาก'],
            ['id' => 'eagle_warrior', 'name' => 'นักรบขี่นกอินทรี', 'milestone_level' => 20, 'hp_bonus' => 55, 'mp_bonus' => 15, 'atk_bonus' => 42, 'def_bonus' => 12, 'ability_name' => 'เวหาโจมตี', 'ability_description' => 'โจมตีเร็วและแรง'],
            ['id' => 'wolf_orc', 'name' => 'นักรบออคขี่หมาป่า', 'milestone_level' => 20, 'hp_bonus' => 95, 'mp_bonus' => 5, 'atk_bonus' => 32, 'def_bonus' => 20, 'ability_name' => 'เลือดนักล่า', 'ability_description' => 'HP สูงและทนทาน'],
            ['id' => 'fire_mage', 'name' => 'จอมเวทไฟ', 'milestone_level' => 20, 'hp_bonus' => 25, 'mp_bonus' => 80, 'atk_bonus' => 48, 'def_bonus' => 5, 'ability_name' => 'เปลวเพลิง', 'ability_description' => 'เวทไฟทำความเสียหายหนัก'],
            ['id' => 'ice_mage', 'name' => 'จอมเวทน้ำแข็ง', 'milestone_level' => 20, 'hp_bonus' => 35, 'mp_bonus' => 70, 'atk_bonus' => 38, 'def_bonus' => 12, 'ability_name' => 'น้ำแข็งคุ้มกัน', 'ability_description' => 'เวทสมดุลระหว่างโจมตีและป้องกัน'],
            ['id' => 'storm_mage', 'name' => 'ผู้ใช้เวทสายฟ้า', 'milestone_level' => 20, 'hp_bonus' => 25, 'mp_bonus' => 75, 'atk_bonus' => 45, 'def_bonus' => 7, 'ability_name' => 'สายฟ้าฟาด', 'ability_description' => 'โจมตีเร็วและรุนแรง'],
            ['id' => 'forest_hunter', 'name' => 'นักล่าป่า', 'milestone_level' => 20, 'hp_bonus' => 45, 'mp_bonus' => 25, 'atk_bonus' => 35, 'def_bonus' => 15, 'ability_name' => 'พรางตัว', 'ability_description' => 'สมดุลระหว่างโจมตีและเอาตัวรอด'],
            ['id' => 'falcon_archer', 'name' => 'พลธนูเหยี่ยว', 'milestone_level' => 20, 'hp_bonus' => 35, 'mp_bonus' => 25, 'atk_bonus' => 43, 'def_bonus' => 9, 'ability_name' => 'เหยี่ยวเล็งเป้า', 'ability_description' => 'ยิงแรงขึ้น'],
            ['id' => 'shadow_marksman', 'name' => 'นักแม่นธนูเงา', 'milestone_level' => 20, 'hp_bonus' => 30, 'mp_bonus' => 35, 'atk_bonus' => 40, 'def_bonus' => 8, 'ability_name' => 'ลูกศรเงา', 'ability_description' => 'โจมตีด้วยเงา'],
        ];

        foreach ($classes as $class) {
            CharacterClass::updateOrCreate(['id' => $class['id']], $class);
        }

        $evolutions = [
            ['from_class_id' => 'normal', 'to_class_id' => 'cavalry', 'required_level' => 10, 'choice_order' => 1],
            ['from_class_id' => 'normal', 'to_class_id' => 'mage', 'required_level' => 10, 'choice_order' => 2],
            ['from_class_id' => 'normal', 'to_class_id' => 'archer', 'required_level' => 10, 'choice_order' => 3],
            ['from_class_id' => 'cavalry', 'to_class_id' => 'dragon_knight', 'required_level' => 20, 'choice_order' => 1],
            ['from_class_id' => 'cavalry', 'to_class_id' => 'eagle_warrior', 'required_level' => 20, 'choice_order' => 2],
            ['from_class_id' => 'cavalry', 'to_class_id' => 'wolf_orc', 'required_level' => 20, 'choice_order' => 3],
            ['from_class_id' => 'mage', 'to_class_id' => 'fire_mage', 'required_level' => 20, 'choice_order' => 1],
            ['from_class_id' => 'mage', 'to_class_id' => 'ice_mage', 'required_level' => 20, 'choice_order' => 2],
            ['from_class_id' => 'mage', 'to_class_id' => 'storm_mage', 'required_level' => 20, 'choice_order' => 3],
            ['from_class_id' => 'archer', 'to_class_id' => 'forest_hunter', 'required_level' => 20, 'choice_order' => 1],
            ['from_class_id' => 'archer', 'to_class_id' => 'falcon_archer', 'required_level' => 20, 'choice_order' => 2],
            ['from_class_id' => 'archer', 'to_class_id' => 'shadow_marksman', 'required_level' => 20, 'choice_order' => 3],
        ];

        foreach ($evolutions as $evolution) {
            ClassEvolution::updateOrCreate(
                [
                    'from_class_id' => $evolution['from_class_id'],
                    'to_class_id' => $evolution['to_class_id'],
                ],
                $evolution
            );
        }

        $skills = [
            ['id' => 'punch', 'class_id' => 'normal', 'name' => 'ชกธรรมดา', 'damage' => 8, 'mana_cost' => 0, 'description' => 'สกิลติดตัวของคนปกติ แรงกว่าโจมตีสุ่มลูกเต๋า 10%'],
            ['id' => 'spear_charge', 'class_id' => 'cavalry', 'name' => 'แทงหอกพุ่งชน', 'damage' => 24, 'mana_cost' => 5, 'description' => 'พุ่งชนด้วยหอก'],
            ['id' => 'fireball', 'class_id' => 'mage', 'name' => 'ลูกไฟ', 'damage' => 30, 'mana_cost' => 8, 'description' => 'ปล่อยเวทไฟใส่ศัตรู'],
            ['id' => 'quick_shot', 'class_id' => 'archer', 'name' => 'ยิงธนูเร็ว', 'damage' => 22, 'mana_cost' => 4, 'description' => 'ยิงธนูด้วยความเร็วสูง'],
            ['id' => 'dragon_crash', 'class_id' => 'dragon_knight', 'name' => 'มังกรพุ่งชน', 'damage' => 48, 'mana_cost' => 12, 'description' => 'พุ่งโจมตีด้วยพลังมังกร'],
            ['id' => 'sky_blade', 'class_id' => 'eagle_warrior', 'name' => 'คมดาบเวหา', 'damage' => 44, 'mana_cost' => 10, 'description' => 'โจมตีจากฟากฟ้า'],
            ['id' => 'wolf_rage', 'class_id' => 'wolf_orc', 'name' => 'หมาป่าคลั่ง', 'damage' => 40, 'mana_cost' => 8, 'description' => 'โจมตีหนักแบบนักล่า'],
            ['id' => 'flame_burst', 'class_id' => 'fire_mage', 'name' => 'ระเบิดเพลิง', 'damage' => 55, 'mana_cost' => 16, 'description' => 'เวทไฟรุนแรง'],
            ['id' => 'frost_lance', 'class_id' => 'ice_mage', 'name' => 'หอกน้ำแข็ง', 'damage' => 45, 'mana_cost' => 12, 'description' => 'แทงศัตรูด้วยน้ำแข็ง'],
            ['id' => 'thunder_arc', 'class_id' => 'storm_mage', 'name' => 'สายฟ้าโค้ง', 'damage' => 50, 'mana_cost' => 15, 'description' => 'ฟาดศัตรูด้วยสายฟ้า'],
            ['id' => 'hunter_mark', 'class_id' => 'forest_hunter', 'name' => 'ตรานักล่า', 'damage' => 38, 'mana_cost' => 8, 'description' => 'เล็งเป้าหมายแล้วโจมตี'],
            ['id' => 'falcon_shot', 'class_id' => 'falcon_archer', 'name' => 'ศรเหยี่ยว', 'damage' => 46, 'mana_cost' => 10, 'description' => 'ยิงศรแรงเหมือนเหยี่ยวโฉบ'],
            ['id' => 'shadow_arrow', 'class_id' => 'shadow_marksman', 'name' => 'ลูกศรเงา', 'damage' => 43, 'mana_cost' => 11, 'description' => 'ลูกศรจากเงามืด'],
        ];

        $skills = array_merge($skills, [
            ['id' => 'punch', 'class_id' => 'normal', 'name' => 'ชกธรรมดา', 'damage' => 8, 'mana_cost' => 0, 'cooldown' => 0, 'description' => 'สกิลติดตัวของคนปกติ แรงกว่าโจมตีสุ่มลูกเต๋า 10%'],
            ['id' => 'guard_punch', 'class_id' => 'normal', 'name' => 'ตั้งการ์ดต่อย', 'damage' => 14, 'mana_cost' => 4, 'cooldown' => 1, 'description' => 'หมัดหนักขึ้น ใช้ MP เล็กน้อย'],
            ['id' => 'lucky_swing', 'class_id' => 'normal', 'name' => 'หวดวัดดวง', 'damage' => 24, 'mana_cost' => 8, 'cooldown' => 2, 'description' => 'โจมตีแรงแต่ต้องรอจังหวะ'],
            ['id' => 'spear_charge', 'class_id' => 'cavalry', 'name' => 'แทงหอกพุ่งชน', 'damage' => 24, 'mana_cost' => 5, 'cooldown' => 0, 'description' => 'พุ่งชนด้วยหอก'],
            ['id' => 'shield_break', 'class_id' => 'cavalry', 'name' => 'หอกทลายเกราะ', 'damage' => 36, 'mana_cost' => 10, 'cooldown' => 1, 'description' => 'โจมตีหนักใส่มอนสเตอร์ตัวเดียว'],
            ['id' => 'warhorse_trample', 'class_id' => 'cavalry', 'name' => 'ม้าศึกกระแทก', 'damage' => 52, 'mana_cost' => 18, 'cooldown' => 2, 'description' => 'ใช้แรงม้าศึกโจมตีรุนแรง'],
            ['id' => 'fireball', 'class_id' => 'mage', 'name' => 'ลูกไฟ', 'damage' => 30, 'mana_cost' => 8, 'cooldown' => 0, 'description' => 'ปล่อยเวทไฟใส่ศัตรู'],
            ['id' => 'mana_bolt', 'class_id' => 'mage', 'name' => 'ศรเวท', 'damage' => 22, 'mana_cost' => 4, 'cooldown' => 0, 'description' => 'เวทเบา ประหยัด MP'],
            ['id' => 'meteor_seed', 'class_id' => 'mage', 'name' => 'เมล็ดอุกกาบาต', 'damage' => 58, 'mana_cost' => 20, 'cooldown' => 2, 'description' => 'เวทหนัก ใช้เมื่ออยากปิดเกม'],
            ['id' => 'quick_shot', 'class_id' => 'archer', 'name' => 'ยิงธนูเร็ว', 'damage' => 22, 'mana_cost' => 4, 'cooldown' => 0, 'description' => 'ยิงธนูด้วยความเร็วสูง'],
            ['id' => 'piercing_arrow', 'class_id' => 'archer', 'name' => 'ศรเจาะเกราะ', 'damage' => 34, 'mana_cost' => 9, 'cooldown' => 1, 'description' => 'เล็งจุดอ่อนและยิงทะลุเกราะ'],
            ['id' => 'rain_arrow', 'class_id' => 'archer', 'name' => 'ฝนธนู', 'damage' => 48, 'mana_cost' => 16, 'cooldown' => 2, 'description' => 'ยิงชุดใหญ่ใส่เป้าหมาย'],
            ['id' => 'dragon_crash', 'class_id' => 'dragon_knight', 'name' => 'มังกรพุ่งชน', 'damage' => 48, 'mana_cost' => 12, 'cooldown' => 0, 'description' => 'พุ่งโจมตีด้วยพลังมังกร'],
            ['id' => 'dragon_guard', 'class_id' => 'dragon_knight', 'name' => 'กรงเล็บมังกร', 'damage' => 68, 'mana_cost' => 22, 'cooldown' => 2, 'description' => 'โจมตีหนักด้วยพลังมังกร'],
            ['id' => 'sky_blade', 'class_id' => 'eagle_warrior', 'name' => 'คมดาบเวหา', 'damage' => 44, 'mana_cost' => 10, 'cooldown' => 0, 'description' => 'โจมตีจากฟากฟ้า'],
            ['id' => 'eagle_dive', 'class_id' => 'eagle_warrior', 'name' => 'อินทรีโฉบ', 'damage' => 62, 'mana_cost' => 18, 'cooldown' => 2, 'description' => 'โจมตีเร็วจากมุมสูง'],
            ['id' => 'wolf_rage', 'class_id' => 'wolf_orc', 'name' => 'หมาป่าคลั่ง', 'damage' => 40, 'mana_cost' => 8, 'cooldown' => 0, 'description' => 'โจมตีหนักแบบนักล่า'],
            ['id' => 'orc_howl', 'class_id' => 'wolf_orc', 'name' => 'คำรามออค', 'damage' => 60, 'mana_cost' => 15, 'cooldown' => 2, 'description' => 'ปลุกพลังสัตว์ป่าแล้วโจมตี'],
            ['id' => 'flame_burst', 'class_id' => 'fire_mage', 'name' => 'ระเบิดเพลิง', 'damage' => 55, 'mana_cost' => 16, 'cooldown' => 0, 'description' => 'เวทไฟรุนแรง'],
            ['id' => 'inferno_core', 'class_id' => 'fire_mage', 'name' => 'แกนเพลิงนรก', 'damage' => 78, 'mana_cost' => 28, 'cooldown' => 2, 'description' => 'เวทไฟระเบิดแรงมาก'],
            ['id' => 'frost_lance', 'class_id' => 'ice_mage', 'name' => 'หอกน้ำแข็ง', 'damage' => 45, 'mana_cost' => 12, 'cooldown' => 0, 'description' => 'แทงศัตรูด้วยน้ำแข็ง'],
            ['id' => 'ice_prison', 'class_id' => 'ice_mage', 'name' => 'คุกน้ำแข็ง', 'damage' => 64, 'mana_cost' => 22, 'cooldown' => 2, 'description' => 'แช่แข็งและโจมตีอย่างหนัก'],
            ['id' => 'thunder_arc', 'class_id' => 'storm_mage', 'name' => 'สายฟ้าโค้ง', 'damage' => 50, 'mana_cost' => 15, 'cooldown' => 0, 'description' => 'ฟาดศัตรูด้วยสายฟ้า'],
            ['id' => 'storm_javelin', 'class_id' => 'storm_mage', 'name' => 'หอกพายุ', 'damage' => 72, 'mana_cost' => 24, 'cooldown' => 2, 'description' => 'รวมสายฟ้าเป็นหอกพุ่งใส่ศัตรู'],
            ['id' => 'hunter_mark', 'class_id' => 'forest_hunter', 'name' => 'ตรานักล่า', 'damage' => 38, 'mana_cost' => 8, 'cooldown' => 0, 'description' => 'เล็งเป้าหมายแล้วโจมตี'],
            ['id' => 'trap_shot', 'class_id' => 'forest_hunter', 'name' => 'ศรวางกับดัก', 'damage' => 56, 'mana_cost' => 16, 'cooldown' => 2, 'description' => 'โจมตีด้วยกลลวงของนักล่า'],
            ['id' => 'falcon_shot', 'class_id' => 'falcon_archer', 'name' => 'ศรเหยี่ยว', 'damage' => 46, 'mana_cost' => 10, 'cooldown' => 0, 'description' => 'ยิงศรแรงเหมือนเหยี่ยวโฉบ'],
            ['id' => 'falcon_storm', 'class_id' => 'falcon_archer', 'name' => 'พายุเหยี่ยว', 'damage' => 66, 'mana_cost' => 20, 'cooldown' => 2, 'description' => 'ยิงต่อเนื่องด้วยจังหวะเหยี่ยว'],
            ['id' => 'shadow_arrow', 'class_id' => 'shadow_marksman', 'name' => 'ลูกศรเงา', 'damage' => 43, 'mana_cost' => 11, 'cooldown' => 0, 'description' => 'ลูกศรจากเงามืด'],
            ['id' => 'night_snipe', 'class_id' => 'shadow_marksman', 'name' => 'ลอบยิงราตรี', 'damage' => 70, 'mana_cost' => 22, 'cooldown' => 2, 'description' => 'ยิงแรงจากมุมมืด'],
        ]);

        foreach ($skills as $skill) {
            Skill::updateOrCreate(['id' => $skill['id']], $skill);
        }

        $monsterFamilies = [
            'slime' => [
                ['name' => 'สไลม์ป่า', 'level' => 1, 'hp' => 45, 'atk' => 7, 'def' => 1, 'exp_reward' => 55, 'sprite_key' => 'slime'],
                ['name' => 'สไลม์ยักษ์', 'level' => 10, 'hp' => 190, 'atk' => 24, 'def' => 9, 'exp_reward' => 330, 'sprite_key' => 'slime'],
                ['name' => 'สไลม์ซอมบี้', 'level' => 20, 'hp' => 430, 'atk' => 48, 'def' => 22, 'exp_reward' => 900, 'sprite_key' => 'slime'],
                ['name' => 'สไลม์ซอมบี้ยักษ์', 'level' => 30, 'hp' => 780, 'atk' => 78, 'def' => 38, 'exp_reward' => 1700, 'sprite_key' => 'slime'],
            ],
            'bat' => [
                ['name' => 'ค้างคาวถ้ำ', 'level' => 3, 'hp' => 70, 'atk' => 11, 'def' => 3, 'exp_reward' => 95, 'sprite_key' => 'bat'],
                ['name' => 'ค้างคาวโลหิต', 'level' => 10, 'hp' => 170, 'atk' => 30, 'def' => 7, 'exp_reward' => 350, 'sprite_key' => 'bat'],
                ['name' => 'ค้างคาวปีศาจ', 'level' => 20, 'hp' => 360, 'atk' => 62, 'def' => 16, 'exp_reward' => 940, 'sprite_key' => 'bat'],
                ['name' => 'ราชาค้างคาวปีศาจ', 'level' => 30, 'hp' => 660, 'atk' => 96, 'def' => 28, 'exp_reward' => 1780, 'sprite_key' => 'bat'],
            ],
            'wolf' => [
                ['name' => 'หมาป่าหิวโซ', 'level' => 6, 'hp' => 120, 'atk' => 18, 'def' => 6, 'exp_reward' => 180, 'sprite_key' => 'wolf'],
                ['name' => 'หมาป่าเขี้ยวเหล็ก', 'level' => 10, 'hp' => 220, 'atk' => 34, 'def' => 11, 'exp_reward' => 390, 'sprite_key' => 'wolf'],
                ['name' => 'หมาป่าเงาจันทร์', 'level' => 20, 'hp' => 470, 'atk' => 68, 'def' => 24, 'exp_reward' => 1040, 'sprite_key' => 'wolf'],
                ['name' => 'จ่าฝูงหมาป่าเงาจันทร์', 'level' => 30, 'hp' => 840, 'atk' => 106, 'def' => 40, 'exp_reward' => 1980, 'sprite_key' => 'wolf'],
            ],
            'skeleton' => [
                ['name' => 'โครงกระดูกเดินดิน', 'level' => 1, 'hp' => 60, 'atk' => 9, 'def' => 3, 'exp_reward' => 70, 'sprite_key' => 'skeleton'],
                ['name' => 'อัศวินโครงกระดูก', 'level' => 10, 'hp' => 210, 'atk' => 28, 'def' => 12, 'exp_reward' => 360, 'sprite_key' => 'skeleton'],
                ['name' => 'แม่ทัพโครงกระดูก', 'level' => 20, 'hp' => 500, 'atk' => 58, 'def' => 30, 'exp_reward' => 1050, 'sprite_key' => 'skeleton'],
                ['name' => 'ราชาโครงกระดูก', 'level' => 30, 'hp' => 920, 'atk' => 92, 'def' => 52, 'exp_reward' => 2050, 'sprite_key' => 'skeleton'],
            ],
            'golem' => [
                ['name' => 'โกเลมดิน', 'level' => 1, 'hp' => 80, 'atk' => 8, 'def' => 7, 'exp_reward' => 85, 'sprite_key' => 'golem'],
                ['name' => 'โกเลมหิน', 'level' => 10, 'hp' => 260, 'atk' => 28, 'def' => 24, 'exp_reward' => 430, 'sprite_key' => 'golem'],
                ['name' => 'โกเลมเหล็ก', 'level' => 20, 'hp' => 620, 'atk' => 56, 'def' => 48, 'exp_reward' => 1200, 'sprite_key' => 'golem'],
                ['name' => 'โกเลมแกนภูเขาไฟ', 'level' => 30, 'hp' => 1120, 'atk' => 88, 'def' => 78, 'exp_reward' => 2300, 'sprite_key' => 'golem'],
            ],
            'wyvern' => [
                ['name' => 'ไวเวิร์นเด็ก', 'level' => 1, 'hp' => 75, 'atk' => 13, 'def' => 4, 'exp_reward' => 100, 'sprite_key' => 'wyvern'],
                ['name' => 'ไวเวิร์นวัยรุ่น', 'level' => 10, 'hp' => 240, 'atk' => 38, 'def' => 14, 'exp_reward' => 470, 'sprite_key' => 'wyvern'],
                ['name' => 'ไวเวิร์นหนุ่ม', 'level' => 20, 'hp' => 480, 'atk' => 55, 'def' => 28, 'exp_reward' => 980, 'sprite_key' => 'wyvern'],
                ['name' => 'ไวเวิร์นคลั่ง', 'level' => 30, 'hp' => 940, 'atk' => 118, 'def' => 44, 'exp_reward' => 2200, 'sprite_key' => 'wyvern'],
            ],
        ];

        foreach ($monsterFamilies as $familyKey => $forms) {
            $previous = null;

            foreach ($forms as $stage => $monster) {
                $previous = Monster::updateOrCreate(
                    ['name' => $monster['name'], 'level' => $monster['level']],
                    array_merge($monster, [
                        'family_key' => $familyKey,
                        'evolution_stage' => $stage,
                        'evolves_from_id' => $previous?->id,
                    ])
                );
            }
        }
    }
}
