(() => {
  function submitForm(form) {
    if (!form) {
      return;
    }
    if (typeof form.requestSubmit === 'function') {
      form.requestSubmit();
      return;
    }
    form.submit();
  }

  function initBlogFilterAutoApply() {
    const form = document.getElementById('pm_blog_filters');
    if (!form) {
      return;
    }

    const searchInput = form.querySelector('input[name="q"]');
    if (searchInput) {
      const listId = searchInput.getAttribute('list');
      const optionValues = new Set();
      if (listId) {
        const dataList = document.getElementById(listId);
        if (dataList) {
          dataList.querySelectorAll('option[value]').forEach((option) => {
            const value = option.getAttribute('value');
            if (!value) {
              return;
            }
            optionValues.add(value.toLowerCase());
          });
        }
      }

      const normalizeSearchValue = () => {
        const value = searchInput.value.trim();
        if (value === '') {
          searchInput.value = '';
          return true;
        }

        const valueLower = value.toLowerCase();
        if (!optionValues.has(valueLower)) {
          return false;
        }

        if (listId) {
          const dataList = document.getElementById(listId);
          if (dataList) {
            const byLower = Array.from(dataList.querySelectorAll('option[value]')).find((option) => {
              const optionValue = option.getAttribute('value') || '';
              return optionValue.toLowerCase() === valueLower;
            });
            if (byLower) {
              searchInput.value = byLower.getAttribute('value') || value;
            }
          }
        }

        return true;
      };

      searchInput.addEventListener('change', () => {
        if (normalizeSearchValue()) {
          submitForm(form);
          return;
        }
        searchInput.setCustomValidity('Select a value from the suggested list.');
        searchInput.reportValidity();
      });

      searchInput.addEventListener('input', () => {
        searchInput.setCustomValidity('');
      });

      form.addEventListener('submit', (event) => {
        if (normalizeSearchValue()) {
          searchInput.setCustomValidity('');
          return;
        }
        event.preventDefault();
        searchInput.setCustomValidity('Select a value from the suggested list.');
        searchInput.reportValidity();
      });
    }

    form.querySelectorAll('.tr-dd-chk').forEach((checkbox) => {
      checkbox.addEventListener('change', () => submitForm(form));
    });

    form.querySelectorAll('.tr-dd-all, .tr-dd-clear').forEach((button) => {
      button.addEventListener('click', () => {
        window.setTimeout(() => submitForm(form), 0);
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBlogFilterAutoApply);
  } else {
    initBlogFilterAutoApply();
  }
})();
