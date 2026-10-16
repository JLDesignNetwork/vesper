/**
 * Vesper front-end entry.
 *
 * DOMPurify sanitizes formatted chat messages before they are inserted into the page
 * (see sanitizeMessageHtml in resources/views/room.blade.php).
 */
import DOMPurify from 'dompurify';

window.DOMPurify = DOMPurify;
