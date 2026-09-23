/**
 * Редакторський бік власних блоків.
 *
 * Написано на ванільному JS через wp.element.createElement, без JSX
 * і без бандлера — так само, як решта скриптів теми. Розмітку в
 * редакторі малює сервер через ServerSideRender: другої реалізації
 * на JS не існує, тож попередній перегляд не може розійтися з тим,
 * що побачить відвідувач.
 */
(function (wp) {
  'use strict';

  var el = wp.element.createElement;
  var registerBlockType = wp.blocks.registerBlockType;
  var InspectorControls = wp.blockEditor.InspectorControls;
  var useBlockProps = wp.blockEditor.useBlockProps;
  var ServerSideRender = wp.serverSideRender;
  var components = wp.components;
  var __ = wp.i18n.__;

  /**
   * Складає поле бічної панелі з опису.
   *
   * @param {Object} props  Пропси блока.
   * @param {Object} field  Опис поля: name, label, type, help, min, max.
   * @return {Object} Елемент контрола.
   */
  function control(props, field) {
    var value = props.attributes[field.name];

    var shared = {
      key: field.name,
      label: field.label,
      help: field.help,
      value: value,
      onChange: function (next) {
        var update = {};
        update[field.name] = next;
        props.setAttributes(update);
      },
    };

    if (field.type === 'number') {
      return el(components.RangeControl, Object.assign({}, shared, {
        min: field.min || 1,
        max: field.max || 12,
        __nextHasNoMarginBottom: true,
      }));
    }

    if (field.type === 'toggle') {
      return el(components.ToggleControl, {
        key: field.name,
        label: field.label,
        help: field.help,
        checked: !!value,
        onChange: shared.onChange,
        __nextHasNoMarginBottom: true,
      });
    }

    if (field.type === 'select') {
      return el(components.SelectControl, Object.assign({}, shared, {
        options: field.options,
        __nextHasNoMarginBottom: true,
      }));
    }

    return el(components.TextControl, Object.assign({}, shared, {
      __nextHasNoMarginBottom: true,
      __next40pxDefaultSize: true,
    }));
  }

  /**
   * Реєструє динамічний блок з панеллю налаштувань.
   *
   * @param {string} name   Імʼя блока.
   * @param {Array}  fields Поля бічної панелі.
   */
  function register(name, fields) {
    registerBlockType(name, {
      edit: function (props) {
        /*
         * useBlockProps обовʼязковий для apiVersion 3. Без нього
         * редактор не бачить кореневого елемента блока: клік по
         * секції виділяє текст, а не сам блок, і панель праворуч
         * так і лишається порожньою.
         */
        var blockProps = useBlockProps({ className: 'brix-block-preview' });

        return el(
          'div',
          blockProps,
          el(
            InspectorControls,
            null,
            el(
              components.PanelBody,
              { title: __('Налаштування блока', 'brix'), initialOpen: true },
              fields.map(function (field) {
                return control(props, field);
              })
            )
          ),
          el(ServerSideRender, {
            block: name,
            attributes: props.attributes,
            EmptyResponsePlaceholder: function () {
              return el(
                components.Placeholder,
                { label: __('Поки що нічого показати', 'brix') },
                __('Блок бере дані з каталогу. Щойно там зʼявиться потрібне — воно буде тут.', 'brix')
              );
            },
          })
        );
      },
      // Розмітку віддає PHP, тож у базі лишається тільки коментар блока.
      save: function () {
        return null;
      },
    });
  }

  register('brix/hero', [
    { name: 'label', label: __('Мітка над заголовком', 'brix') },
    { name: 'heading', label: __('Заголовок', 'brix') },
    { name: 'accent', label: __('Що підсвітити вишневим', 'brix'), help: __('Частина заголовка. Має збігатися дослівно.', 'brix') },
    { name: 'text', label: __('Текст', 'brix') },
    { name: 'primaryLabel', label: __('Головна кнопка', 'brix') },
    { name: 'primaryUrl', label: __('Її посилання', 'brix'), help: __('Порожнє — веде в магазин.', 'brix') },
    { name: 'secondaryLabel', label: __('Друга кнопка', 'brix') },
    { name: 'secondaryUrl', label: __('Її посилання', 'brix'), help: __('Порожнє — веде на квіз.', 'brix') },
    { name: 'showLot', label: __('Показувати лот тижня', 'brix'), type: 'toggle' },
  ]);

  register('brix/lot-grid', [
    { name: 'label', label: __('Мітка', 'brix') },
    { name: 'heading', label: __('Заголовок', 'brix') },
    { name: 'limit', label: __('Скільки лотів', 'brix'), type: 'number', min: 1, max: 12 },
    {
      name: 'category',
      label: __('Лінійка', 'brix'),
      type: 'select',
      options: (window.brixBlockData && window.brixBlockData.categories) || [],
    },
    { name: 'showLink', label: __('Посилання на каталог', 'brix'), type: 'toggle' },
  ]);

  register('brix/marquee', [
    { name: 'limit', label: __('Скільки нот', 'brix'), type: 'number', min: 4, max: 24 },
  ]);

  register('brix/feature', [
    { name: 'label', label: __('Мітка', 'brix') },
    { name: 'heading', label: __('Заголовок', 'brix') },
    { name: 'text', label: __('Текст', 'brix') },
    { name: 'buttonLabel', label: __('Кнопка', 'brix') },
    { name: 'buttonUrl', label: __('Посилання кнопки', 'brix') },
    {
      name: 'media',
      label: __('Що праворуч', 'brix'),
      type: 'select',
      options: [
        { label: __('Фото', 'brix'), value: 'photo' },
        { label: __('Шкала °Bx', 'brix'), value: 'brix-scale' },
      ],
    },
    {
      name: 'photo',
      label: __('Фото', 'brix'),
      help: __('Без фото секція показує плашку тону нижче.', 'brix'),
      type: 'select',
      options: [
        { label: __('Без фото', 'brix'), value: '' },
        { label: __('Способи заварювання', 'brix'), value: 'quiz' },
        { label: __('Пачки в коробці', 'brix'), value: 'club' },
        { label: __('Бар кав’ярні', 'brix'), value: 'wholesale' },
      ],
    },
    {
      name: 'tone',
      label: __('Тон фото', 'brix'),
      type: 'select',
      options: [
        { label: __('Теплий', 'brix'), value: 'warm' },
        { label: __('Вишневий', 'brix'), value: 'cherry' },
        { label: __('Зелений', 'brix'), value: 'green' },
        { label: __('Темний', 'brix'), value: 'dark' },
      ],
    },
    { name: 'dark', label: __('Темна секція', 'brix'), type: 'toggle' },
    { name: 'reversed', label: __('Текст праворуч', 'brix'), type: 'toggle' },
  ]);

  register('brix/farm-teaser', [
    { name: 'label', label: __('Мітка', 'brix') },
    { name: 'heading', label: __('Заголовок', 'brix') },
    { name: 'limit', label: __('Скільки виробників', 'brix'), type: 'number', min: 1, max: 8 },
  ]);

  register('brix/brew-guide', [
    { name: 'label', label: __('Мітка', 'brix') },
    { name: 'heading', label: __('Заголовок', 'brix') },
    { name: 'limit', label: __('Скільки рецептів', 'brix'), type: 'number', min: 1, max: 8 },
  ]);
})(window.wp);
