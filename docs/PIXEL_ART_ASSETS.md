# Pixel Art Asset Pack

Generated for Dragon Path Online MVP art direction.

## Files

Transparent PNGs ready for Phaser:

- `public/assets/generated/pixel-art/player-classes-sheet.png`
  - 4 rows x 4 frames.
  - Rows: adventurer, mounted knight, mage, archer.
  - Use for idle/attack placeholders and class preview.
- `public/assets/generated/pixel-art/monster-evolution-sheet.png`
  - 6 monster families x 4 evolution stages.
  - Families: slime, bat, wolf, skeleton, golem, wyvern.
  - Use stage by level milestone. Boss encounters can use the next stage.
- `public/assets/generated/pixel-art/world-dungeon-tileset.png`
  - World tiles, dungeon tiles, lava tiles, colosseum tiles, props, portals, torches.
  - Use as a visual placeholder tileset before final hand-authored maps.
- `public/assets/generated/pixel-art/fantasy-ui-pack.png`
  - Buttons, panels, HP/MP bars, dice icons, skill icons, potion icons, elemental badges.
  - Use for combat controls, dice roll feedback, and HUD polish.
- `public/assets/generated/pixel-art/pixel-art-preview.png`
  - Quick preview sheet for review only.

Original chroma-key files are stored in:

- `public/assets/generated/pixel-art/source/`

## Notes

- All exported working files are `1536x1024` PNG with alpha transparency.
- The generated sheets are good for MVP polish, but should be sliced and cleaned before final production.
- Recommended next step:
  1. Slice player and monster sheets into individual frames.
  2. Register Phaser texture atlases or frame configs.
  3. Replace current CSS placeholder characters with sprites.
  4. Use the tileset for the walkable world map, dungeon battle scene, and colosseum PVP scene.
  5. Use the UI pack for dice, HP/MP bars, buttons, skill icons, and element badges.
