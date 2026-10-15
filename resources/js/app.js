import { startGame } from './game';
import { startEditor } from './editor';

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('game')) startGame();
    if (document.getElementById('editor')) startEditor();
});
