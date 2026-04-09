/**
 * Safari TikTok Fix — dainis.net
 * 2026-04-09
 *
 * Safari ITP blocks TikTok's cross-site storage. The official blockquote embed
 * briefly shows the player (~1.4s) then collapses to a text shell.
 *
 * This script runs on every page. In Safari only, it finds all .tiktok-embed
 * blockquotes on DOMContentLoaded, replaces them with placeholder divs before
 * embed.js can process them, then fetches thumbnails via the server-side proxy
 * and renders a thumbnail + "Watch on TikTok" overlay link.
 *
 * Chrome/Firefox: exits immediately (no-op).
 */
(function () {
  'use strict';

  if (!/^((?!chrome|android).)*safari/i.test(navigator.userAgent)) return;

  function esc(s) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(s));
    return d.innerHTML;
  }

  function renderThumbnail(placeholder, url) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', safariTikTokFix.ajaxurl + '?action=works_tiktok_thumb&url=' + encodeURIComponent(url), true);
    xhr.onload = function () {
      try {
        var resp = JSON.parse(xhr.responseText);
        var thumb = (resp.success && resp.data && resp.data.thumbnail_url) ? resp.data.thumbnail_url : '';
        var title = (resp.success && resp.data && resp.data.title) ? resp.data.title : '';
        if (thumb) {
          placeholder.innerHTML =
            '<a href="' + esc(url) + '" target="_blank" rel="noopener" style="display:block;text-decoration:none;">' +
            '<div style="position:relative;display:inline-block;max-width:75%;">' +
            '<img src="' + esc(thumb) + '" alt="' + esc(title) + '" style="max-width:100%;border-radius:8px;display:block;">' +
            '<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:rgba(0,0,0,0.65);color:#fff;padding:10px 18px;border-radius:4px;font-size:14px;white-space:nowrap;">&#9654; Watch on TikTok</div>' +
            '</div>' +
            (title ? '<p style="margin:6px 0 0;font-size:13px;color:#555;">' + esc(title) + '</p>' : '') +
            '</a>';
        } else {
          placeholder.innerHTML = '<a href="' + esc(url) + '" target="_blank" rel="noopener" style="font-size:14px;">&#9654; Watch on TikTok</a>';
        }
      } catch (e) {
        placeholder.innerHTML = '<a href="' + esc(url) + '" target="_blank" rel="noopener" style="font-size:14px;">&#9654; Watch on TikTok</a>';
      }
    };
    xhr.onerror = function () {
      placeholder.innerHTML = '<a href="' + esc(url) + '" target="_blank" rel="noopener" style="font-size:14px;">&#9654; Watch on TikTok</a>';
    };
    xhr.send();
  }

  document.addEventListener('DOMContentLoaded', function () {
    var embeds = document.querySelectorAll('.tiktok-embed');
    embeds.forEach(function (el) {
      var url = el.getAttribute('cite');
      if (!url) return;
      var placeholder = document.createElement('div');
      placeholder.style.cssText = 'text-align:center;margin:12px auto;max-width:605px;';
      placeholder.innerHTML = '<span style="color:#999;font-size:0.85em;">Loading\u2026</span>';
      el.parentNode.replaceChild(placeholder, el);
      renderThumbnail(placeholder, url);
    });
  });
})();
