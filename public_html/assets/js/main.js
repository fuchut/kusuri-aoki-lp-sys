// 外部でも使う変数
let pickups_swiper;

if ($('#loader')[0]) {
  $('body')
    .addClass('loader-fix')
    .queue(function () {
      $('#loader > div').css('opacity', 1);
    });
}

let scrollPos = 0;

/************************************
 * サイト公開時には削除する
 ***********************************/
// 文字の増減による表示崩れ確認用
//  $(function(){
//   $('body *').prop('contenteditable', 'true');
//  });

/************************************
 * UA
 ***********************************/
const ua = window.navigator.userAgent.toLowerCase();
const isTouchDevice = 'ontouchend' in document;

/***********************************************
 * 使用する
 **********************************************/
$(function () {
  loader();
  telLink();
  // smoothScroll();
  containerSmoothScroll();
  phantom(undefined, undefined, true);
  select();
  faqAccordion();
  bottleSelection();
  entryFormInput();
});

/***********************************************
 * Loader
 **********************************************/
function loader() {
  if ($('#loader')[0]) {
    $('#loader > div')
      .fadeOut(500)
      .queue(function () {
        $('body').removeClass('loader-fix');
        $('#loader').fadeOut(1000);
      });
  }
}

/***********************************************
 * Tel Link
 **********************************************/
function telLink() {
  if (ua.indexOf('iphone') > 0 || ua.indexOf('android') > 0) {
    if ($('.tel-link')[0]) {
      $('.tel-link').each(function () {
        const tel_no = $(this).attr('data-telno');
        $(this).wrap('<a href="tel:' + tel_no + '">');
      });
    }
  }
}

/***********************************************
 * Smooth scroll
 **********************************************/
/*
function smoothScroll() {
  $('[href^="#"]').on('click', function () {
    const speed = 500;
    const href = $(this).attr('href');
    const target = $(href == '#' || href == '' ? 'html' : href);
    let position = target.offset().top;
    if (target != '#top' && $('header').css('position') == 'fixed') {
      position -= $('header').height();
    }
    $('html, body').animate({ scrollTop: position }, 600);
    return false;
  });

  const urlHash = location.hash;
  if (urlHash) {
    if ($(urlHash)[0]) {
      let position = $(urlHash).offset().top;
      if ($('header').css('position') == 'fixed') {
        position -= $('header').height();
      }
      $('html, body').animate({ scrollTop: position }, 600);
    }
  }
}
*/

function containerSmoothScroll() {

  const $container = $('.lp-layout__center');

  $('[href^="#"]').on('click', function(e){
    e.preventDefault();

    const targetId = $(this).attr('href').replace('#', '');
    const $target = $('#' + targetId);

    if ($target.length === 0) return;

    // コンテナ基準のスクロール位置を計算
    const containerTop  = $container[0].getBoundingClientRect().top;
    const targetTop     = $target[0].getBoundingClientRect().top;

    const scrollPos = $container.scrollTop() + (targetTop - containerTop);

    // スムーススクロール
    $container.animate(
      { scrollTop: scrollPos },
      500,
      'swing'
    );
  });
}

/***********************************************
 * phantom
 **********************************************/
function phantom(searchClassName = '.phantom', addClassName = 'phantom-animation', repeat = false) {
  const phantomList = document.querySelectorAll(searchClassName);
  const phantom = Array.prototype.slice.call(phantomList, 0);

  const observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.intersectionRatio > 0) {
        entry.target.classList.add(addClassName);

        if (!repeat) {
          observer.unobserve(entry.target);
        }
      } else {
        if (repeat) {
          entry.target.classList.remove(addClassName);
        }
      }
    });
  });

  phantom.forEach(function (e) {
    observer.observe(e);
  });
}

/**********************************************
 * modal
 **********************************************/
function modaal(e) {
  $(e).modaal({
    type: 'image',
  });
}


// /**********************************************
//  * select
//  **********************************************/

function select() {
  if ($('.select-wrap')[0]) {
    $('.select-wrap select').each(function () {
      if ($(this).prop('selectedIndex') == '0') $(this).parent('.select-wrap').removeClass('changed');
      else $(this).parent('.select-wrap').addClass('changed');
    });

    $('.select-wrap select').on('change', function () {
      if ($(this).prop('selectedIndex') == '0') $(this).parent('.select-wrap').removeClass('changed');
      else $(this).parent('.select-wrap').addClass('changed');
    });
  }
}

/**********************************************
 * FAQ Accordion
 **********************************************/
function faqAccordion() {
  if ($('.js-faq-question')[0]) {
    const speed = 300;
    
    $('.js-faq-question').on('click', function () {
      const $answer = $(this).siblings('.js-faq-answer');
      const $item = $(this).closest('.lp-faq-section__content-item');
      
      if ($item.hasClass('is-open')) {
        // 閉じる
        $item.removeClass('is-open');
        $answer.slideUp(speed);
      } else {
        // 開く
        // 他の開いているFAQを閉じる（オプション）
        $('.lp-faq-section__content-item.is-open').removeClass('is-open');
        $('.js-faq-answer').slideUp(speed);
        
        $item.addClass('is-open');
        $answer.slideDown(speed);
      }
    });
  }
}

/**********************************************
 * Bottle Selection
 **********************************************/
function bottleSelection() {

  if($("[data-selected-goods]")[0]) {
    // 初期値選択
    const selectedGoods = $("[data-selected-goods]").data("selected-goods");
    $('[name="present"][value="'+selectedGoods+'"]').prop('checked', true);
  }

  if ($('.js-bottle-item')[0]) {
    const $bottleItem = $('.js-bottle-item');
    const $bottleOptions = $('.js-bottle-options');
    const $bottleRadios = $('.js-bottle-radio');
    
    // 水筒カード全体をクリックしたとき
    $bottleItem.on('click', function(e) {
      // ラジオボタンやラベルをクリックした場合は処理しない
      if ($(e.target).closest('.js-bottle-options').length > 0) {
        return;
      }
      
      // オプションを表示
      $bottleOptions.addClass('is-visible');
      $bottleItem.addClass('is-selected');
    });
    
    // 他の商品が選択されたら水筒の選択を解除
    $('input[name="goods"]').on('change', function() {
      const isBottleSelected = $(this).hasClass('js-bottle-radio');
      
      if (!isBottleSelected) {
        // 水筒以外が選択されたら
        $bottleOptions.removeClass('is-visible');
        $bottleItem.removeClass('is-selected');
        $bottleRadios.prop('checked', false);
      }
    });
    
    // 水筒の色が選択されたとき
    $bottleRadios.on('change', function() {
      if ($(this).is(':checked')) {
        $bottleItem.addClass('is-selected');
      }
    });
  }
}

/**********************************************
 * Entry Form Input Formatting
 **********************************************/
function entryFormInput() {
  // Aoca番号のフォーマット（4桁ごとにスペース）
  const $aocaInput = $('input[name="member_id"]');
  if ($aocaInput.length > 0) {

    $aocaInput.on('input', function() {

      const $input = $(this);
      const value = $input.val();
      const cursorPos = $input[0].selectionStart;
      
      // 数字以外を除去
      let numbersOnly = value.replace(/\D/g, '');
      
      // 最大16桁まで
      if (numbersOnly.length > 16) {
        numbersOnly = numbersOnly.substring(0, 16);
      }
      
      // 4桁ごとにスペースを挿入
      let formatted = '';
      for (let i = 0; i < numbersOnly.length; i++) {
        if (i > 0 && i % 4 === 0) {
          formatted += ' ';
        }
        formatted += numbersOnly[i];
      }
      
      // 値を設定
      $input.val(formatted);
      
      // カーソル位置を調整
      // スペースが挿入された場合、カーソル位置を調整
      const beforeCursor = value.substring(0, cursorPos);
      const beforeCursorNumbers = beforeCursor.replace(/\D/g, '').length;
      
      // フォーマット後の位置を計算
      const spacesBeforeCursor = Math.floor(beforeCursorNumbers / 4);
      const newCursorPos = beforeCursorNumbers + spacesBeforeCursor;
      
      // カーソル位置を設定
      $input[0].setSelectionRange(newCursorPos, newCursorPos);
    });

    $aocaInput.trigger('input');
    
    // バックスペース時の処理
    $aocaInput.on('keydown', function(e) {
      const $input = $(this);
      const value = $input.val();
      const cursorPos = $input[0].selectionStart;
      
      // バックスペースまたはDeleteキー
      if (e.keyCode === 8 || e.keyCode === 46) {
        // カーソル位置がスペースの直後にある場合、スペースをスキップ
        if (cursorPos > 0 && value[cursorPos - 1] === ' ') {
          e.preventDefault();
          const newPos = cursorPos - 1;
          $input[0].setSelectionRange(newPos, newPos);
          $input.trigger('input');
        }
      }
    });
  }
  
  // 電話番号の入力制限（数字と-のみ）
  const $phoneInput = $('input[name="phone"]');
  
  function restrictToNumbersAndHyphen($input) {
    $input.on('input', function() {
      const value = $(this).val();
      // 数字と-以外を除去
      const filtered = value.replace(/[^0-9-]/g, '');
      if (value !== filtered) {
        $(this).val(filtered);
      }
    });
    
    // キーダウンで数字と-以外を無効化
    $input.on('keydown', function(e) {
      // 許可するキー: 数字、-、Backspace、Delete、Tab、Arrow keys、Home、End
      const allowedKeys = [
        8,   // Backspace
        9,   // Tab
        35,  // End
        36,  // Home
        37,  // Left Arrow
        38,  // Up Arrow
        39,  // Right Arrow
        40,  // Down Arrow
        45,  // Insert
        46   // Delete
      ];
      
      // Ctrl/Cmd + A, C, V, X を許可
      if (e.ctrlKey || e.metaKey) {
        if ([65, 67, 86, 88].indexOf(e.keyCode) !== -1) {
          return true;
        }
      }
      
      // 数字キー（0-9）
      if (e.keyCode >= 48 && e.keyCode <= 57) {
        return true;
      }
      
      // テンキーの数字（0-9）
      if (e.keyCode >= 96 && e.keyCode <= 105) {
        return true;
      }
      
      // ハイフン（-）
      if (e.keyCode === 189 || e.keyCode === 109) {
        return true;
      }
      
      // 許可されたキーの場合
      if (allowedKeys.indexOf(e.keyCode) !== -1) {
        return true;
      }
      
      // それ以外は無効化
      e.preventDefault();
      return false;
    });
    
    // ペースト時の処理
    $input.on('paste', function(e) {
      e.preventDefault();
      const pastedText = (e.originalEvent || e).clipboardData.getData('text/plain');
      // 数字と-以外を除去
      const filtered = pastedText.replace(/[^0-9-]/g, '');
      const currentValue = $(this).val();
      const cursorPos = $(this)[0].selectionStart;
      const newValue = currentValue.substring(0, cursorPos) + filtered + currentValue.substring($(this)[0].selectionEnd);
      $(this).val(newValue);
      // カーソル位置を調整
      const newCursorPos = cursorPos + filtered.length;
      $(this)[0].setSelectionRange(newCursorPos, newCursorPos);
    });
  }
  
  if ($phoneInput.length > 0) {
    restrictToNumbersAndHyphen($phoneInput);
  }
}
