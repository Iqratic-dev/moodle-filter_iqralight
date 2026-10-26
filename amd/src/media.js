// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Loads deferred media when the learner taps, or straight away in "load everything" mode.
 *
 * The connection mode comes from the classes set by theme_iqralight on the body. Without that
 * theme, every deferred media waits for a tap.
 *
 * @module     filter_iqralight/media
 * @copyright  2026 IQRATIC
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {notifyFilterContentUpdated} from 'core_filters/events';

const WRAPPER = '[data-filter-iqra]';

/**
 * Copies the deferred addresses back onto an element.
 *
 * @param {HTMLElement} element
 */
const restore = (element) => {
    ['src', 'data', 'srcset'].forEach((name) => {
        const deferred = element.getAttribute(`data-iqra-${name}`);
        if (deferred !== null) {
            element.setAttribute(name, deferred);
            element.removeAttribute(`data-iqra-${name}`);
        }
    });
};

/**
 * Loads the media held by a placeholder.
 *
 * @param {HTMLElement} wrapper
 * @param {Object} strings
 */
const load = (wrapper, strings) => {
    if (!wrapper || wrapper.classList.contains('filter-iqra-loaded')) {
        return;
    }
    let media = wrapper.firstElementChild;
    if (!media || media.classList.contains('filter-iqra-placeholder')) {
        return;
    }
    if (media.tagName === 'TEMPLATE') {
        // A player built by Moodle: it is inserted now, and Moodle sets it up (and loads
        // its script) through the usual event for new content.
        const content = media.content.cloneNode(true);
        const nodes = Array.from(content.children);
        media.replaceWith(content);
        wrapper.classList.add('filter-iqra-loaded');
        notifyFilterContentUpdated(nodes);
        return;
    }
    restore(media);
    media.querySelectorAll('[data-iqra-src]').forEach(restore);
    if (media.tagName === 'OBJECT') {
        // Browsers only read an object's address when it is inserted.
        const clone = media.cloneNode(true);
        media.replaceWith(clone);
        media = clone;
    }
    // A linked image waits with its link: the error to watch is the image's.
    const watched = media.tagName === 'A' ? (media.querySelector('img') || media) : media;
    watched.addEventListener('error', () => {
        wrapper.classList.remove('filter-iqra-loaded');
        const message = document.createElement('span');
        message.className = 'filter-iqra-error';
        message.textContent = strings.loadfailed;
        wrapper.querySelector('.filter-iqra-placeholder').append(message);
    }, {once: true});
    wrapper.classList.add('filter-iqra-loaded');
};

/**
 * Text-only option: light images not yet downloaded are replaced by a button.
 *
 * @param {Object} strings
 */
const applyTextOnly = (strings) => {
    document.querySelectorAll('img[data-iqra-small]').forEach((image) => {
        if (image.complete && image.naturalWidth > 0) {
            return;
        }
        image.setAttribute('data-iqra-src', image.getAttribute('src'));
        image.removeAttribute('src');
        if (image.hasAttribute('srcset')) {
            image.setAttribute('data-iqra-srcset', image.getAttribute('srcset'));
            image.removeAttribute('srcset');
        }
        const wrapper = document.createElement('span');
        wrapper.className = 'filter-iqra';
        wrapper.setAttribute('data-filter-iqra', 'image');
        const placeholder = document.createElement('span');
        placeholder.className = 'filter-iqra-placeholder';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-secondary filter-iqra-load';
        button.dataset.action = 'filter_iqralight/load';
        button.textContent = strings.showimage;
        placeholder.append(button);
        // A linked image is wrapped with its link, so that the button never sits inside a link.
        const parent = image.parentElement;
        const media = parent && parent.tagName === 'A' && parent.children.length === 1 ? parent : image;
        media.replaceWith(wrapper);
        wrapper.append(media, placeholder);
    });
};

/**
 * Whether the theme says everything may load straight away.
 *
 * @returns {boolean}
 */
const loadsEverything = () => document.body.classList.contains('iqra-mode-full') &&
    !document.body.classList.contains('iqra-textonly');

/**
 * Entry point, called once per page by the filter.
 *
 * @param {Object} strings Localised labels.
 */
export const init = (strings) => {
    if (document.body.classList.contains('iqra-textonly')) {
        applyTextOnly(strings);
    }
    if (loadsEverything()) {
        document.querySelectorAll(WRAPPER).forEach((wrapper) => load(wrapper, strings));
    }
    document.addEventListener('click', (e) => {
        const button = e.target.closest('[data-action="filter_iqralight/load"]');
        if (button) {
            e.preventDefault();
            load(button.closest(WRAPPER), strings);
        }
    });
    // When the learner switches to "load everything", load what is waiting.
    new MutationObserver(() => {
        if (loadsEverything()) {
            document.querySelectorAll(WRAPPER).forEach((wrapper) => load(wrapper, strings));
        }
    }).observe(document.body, {attributes: true, attributeFilter: ['class']});
};
