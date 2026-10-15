{# TEMPLATE: lista padrão do admin (combinar com controller-crud-admin.php.tpl) #}
{# Placeholders: {{heading...}} vêm da language via controller; items = listagem paginada #}
{{ header }}{{ column_left }}
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <div class="float-end">
        <a href="{{ add }}" class="btn btn-primary">{{ button_add }}</a>
        <button type="submit" form="form-item" formaction="{{ delete }}"
                class="btn btn-danger" data-bs-toggle="tooltip" title="{{ button_delete }}">
          {{ button_delete }}
        </button>
      </div>
      <h1>{{ heading_title }}</h1>
      <ol class="breadcrumb">
        {% for breadcrumb in breadcrumbs %}
          <li class="breadcrumb-item"><a href="{{ breadcrumb.href }}">{{ breadcrumb.text }}</a></li>
        {% endfor %}
      </ol>
    </div>
  </div>

  <div class="container-fluid">
    {% if success %}
      <div class="alert alert-success">{{ success }}</div>
    {% endif %}

    <div class="card">
      <div class="card-header"><h3 class="card-title">{{ text_list }}</h3></div>
      <div class="card-body">
        <form id="form-item" method="post">
          <input type="hidden" name="csrf_token" value="{{ csrf_token }}">
          <table class="table table-bordered table-hover">
            <thead>
              <tr>
                <th class="text-center" style="width:1px">
                  <input type="checkbox" onclick="document.querySelectorAll('input[name*=\'selected\']').forEach((el) => el.checked = this.checked)">
                </th>
                <th>{{ column_name }}</th>
                <th>{{ column_status }}</th>
                <th class="text-end">{{ column_sort_order }}</th>
                <th class="text-end">{{ column_action }}</th>
              </tr>
            </thead>
            <tbody>
              {% for item in items %}
                <tr>
                  <td class="text-center"><input type="checkbox" name="selected[]" value="{{ item.id }}"></td>
                  <td>{{ item.name }}</td>
                  <td>{{ item.status ? text_enabled : text_disabled }}</td>
                  <td class="text-end">{{ item.sort_order }}</td>
                  <td class="text-end"><a href="{{ item.edit }}" class="btn btn-sm btn-primary">{{ text_edit }}</a></td>
                </tr>
              {% else %}
                <tr><td colspan="5" class="text-center">{{ text_no_results }}</td></tr>
              {% endfor %}
            </tbody>
          </table>
        </form>

        <div class="row">
          <div class="col-sm-6 text-start">{{ pagination }}</div>
          <div class="col-sm-6 text-end">{{ results }}</div>
        </div>
      </div>
    </div>
  </div>
</div>
{{ footer }}
