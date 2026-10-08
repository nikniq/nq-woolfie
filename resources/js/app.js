import { startGame } from './game';

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('game')) {
        startGame();
    }
});
