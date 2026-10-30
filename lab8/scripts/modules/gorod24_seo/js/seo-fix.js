/**
 * @file
 * Заплатка для SEO-виджета yoast_seo 2.2.x на Drupal 11.
 *
 * Две проблемы виджета:
 * 1. Обработчик updateSeoData вызывает snippetTitle.attr() — jQuery-метод у
 *    DOM-узла: TypeError, элемент #snippet_title не появляется в разметке,
 *    анализ не пересчитывается. Скрипт гасит правку сниппета (enable_editing).
 * 2. Form API рендерит input[type=hidden] без атрибута id, а библиотека ищет
 *    поля по id из drupalSettings. Скрипт проставляет id до первого анализа.
 */
(function () {
  'use strict';

  function patchSettings() {
    var settings = window.drupalSettings && window.drupalSettings.yoast_seo;
    if (!settings) {
      return;
    }
    if (settings.enable_editing) {
      settings.enable_editing.title = false;
      settings.enable_editing.description = false;
    }
  }

  var names = {
    edit_title: 'field_yoast_seo[0][edit_title]',
    edit_description: 'field_yoast_seo[0][edit_description]',
    seo_status: 'field_yoast_seo[0][status]'
  };

  function fixIds() {
    var settings = window.drupalSettings && window.drupalSettings.yoast_seo;
    if (!settings || !settings.fields) {
      return;
    }
    Object.keys(names).forEach(function (key) {
      var wanted = settings.fields[key];
      if (!wanted || document.getElementById(wanted)) {
        return;
      }
      var input = document.querySelector('input[name="' + names[key] + '"]');
      if (input) {
        input.id = wanted;
      }
    });
  }

  patchSettings();

  if (window.Drupal && window.Drupal.behaviors) {
    window.Drupal.behaviors.gorod24SeoFix = {
      attach: function () {
        patchSettings();
        fixIds();
      }
    };
  }
})();
