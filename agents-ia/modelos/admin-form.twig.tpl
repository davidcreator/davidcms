{# TEMPLATE: formulário padrão do admin com abas por idioma (padrão *_description) #}
{{ header }}{{ column_left }}
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <div class="float-end">
        <button type="submit" form="form-item" class="btn btn-primary">{{ button_save }}</button>
        <a href="{{ back }}" class="btn btn-light">{{ button_back }}</a>
      </div>
      <h1>{{ heading_title }}</h1>
    </div>
  </div>

  <div class="container-fluid">
    {% if error.warning %}
      <div class="alert alert-danger">{{ error.warning }}</div>
    {% endif %}

    <div class="card">
      <div class="card-header"><h3 class="card-title">{{ text_form }}</h3></div>
      <div class="card-body">
        <form id="form-item" action="{{ action }}" method="post">
          <input type="hidden" name="csrf_token" value="{{ csrf_token }}">
          <input type="hidden" name="{{ entity }}_id" value="{{ item.id ?? 0 }}">

          {# Abas de idioma — um registro por language_id #}
          <ul class="nav nav-tabs">
            {% for language in languages %}
              <li class="nav-item">
                <a href="#tab-language-{{ language.language_id }}" data-bs-toggle="tab"
                   class="nav-link {{ loop.first ? 'active' }}">{{ language.name }}</a>
              </li>
            {% endfor %}
          </ul>

          <div class="tab-content">
            {% for language in languages %}
              <div class="tab-pane {{ loop.first ? 'active' }}" id="tab-language-{{ language.language_id }}">
                <div class="mb-3">
                  <label class="form-label">{{ entry_name }}</label>
                  <input type="text"
                         name="{{ entity }}_description[{{ language.language_id }}][name]"
                         value="{{ item.descriptions[language.language_id].name ?? '' }}"
                         class="form-control {{ error.name ? 'is-invalid' }}">
                  {% if error.name %}<div class="invalid-feedback">{{ error.name }}</div>{% endif %}
                </div>
                <div class="mb-3">
                  <label class="form-label">{{ entry_description }}</label>
                  <textarea name="{{ entity }}_description[{{ language.language_id }}][description]"
                            rows="6" class="form-control">{{ item.descriptions[language.language_id].description ?? '' }}</textarea>
                </div>
              </div>
            {% endfor %}
          </div>

          <hr>
          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label">{{ entry_image }}</label>
              <input type="text" name="image" value="{{ item.image ?? '' }}" class="form-control">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">{{ entry_status }}</label>
              <select name="status" class="form-select">
                <option value="1" {{ (item.status ?? 1) ? 'selected' }}>{{ text_enabled }}</option>
                <option value="0" {{ not (item.status ?? 1) ? 'selected' }}>{{ text_disabled }}</option>
              </select>
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">{{ entry_sort_order }}</label>
              <input type="number" name="sort_order" value="{{ item.sort_order ?? 0 }}" class="form-control">
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
{{ footer }}
