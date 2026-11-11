@php
  $linkLabel = match($item->link_type) {
      'route' => 'route:'.$item->route_name,
      'url' => $item->url,
      default => 'Nhóm chứa',
  };
@endphp
@php
  $scopeKey = $item->parent_id === null ? 'root' : (string) $item->parent_id;
  $scopeIds = array_values(array_map('intval', $scopeIdsByParent[$scopeKey] ?? []));
  $scopeIndex = array_search((int) $item->id, $scopeIds, true);
  $ancestorLabels = $ancestorLabels ?? [];
  $ancestorActive = (bool) ($ancestorActive ?? true);
  $breadcrumb = implode(' / ', [...$ancestorLabels, $item->name_vi]);
  $moveItem = $moveCatalogById->get($item->id);
  $orders = [];
  foreach (['up' => -1, 'down' => 1] as $direction => $offset) {
      $target = $scopeIndex === false ? -1 : $scopeIndex + $offset;
      if ($target >= 0 && $target < count($scopeIds)) {
          $next = $scopeIds;
          [$next[$scopeIndex], $next[$target]] = [$next[$target], $next[$scopeIndex]];
          $orders[$direction] = $next;
      }
  }
@endphp
<tr data-menu-row data-menu-id="{{ $item->id }}" data-menu-level="{{ $level }}" data-menu-parent="{{ $item->parent_id ?? '' }}" data-menu-location="{{ $item->location }}" data-menu-status="{{ $item->status ? '1' : '0' }}" data-scope-ids="{{ implode(',', $scopeIds) }}">
  <td class="text-muted">{{ $item->id }}</td>
  <td>
    <div class="d-flex align-items-start gap-2" style="padding-left: {{ $level * 1.5 }}rem">
      @if($level > 0)<i class="fa-solid fa-turn-up fa-rotate-90 text-muted mt-1" aria-hidden="true"></i>@endif
      <div>
        <div class="d-flex flex-wrap align-items-center gap-2"><strong>{{ $item->name_vi }}</strong><span class="badge text-bg-light border">Cấp {{ $level + 1 }}</span></div>
        <div class="small text-muted">{{ $item->name_en }}</div>
        @if($level > 0)<div class="small text-muted text-break" title="Đường dẫn cây">{{ $breadcrumb }}</div>@endif
      </div>
    </div>
  </td>
  <td><span class="badge text-bg-light border">{{ config('menus.link_types.'.$item->link_type) }}</span><div class="small text-muted text-break mt-1">{{ $linkLabel }}</div></td>
  <td data-menu-sort-order>{{ $item->sort_order }}</td>
  <td><span data-menu-status-badge class="badge {{ $item->status ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $item->status ? 'Hiển thị' : 'Đang ẩn' }}</span>@if($level > 0)<div data-parent-hidden-warning class="small text-warning mt-1 {{ $ancestorActive ? 'd-none' : '' }}"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Ancestor đang ẩn</div>@endif</td>
  <td>
    <div class="d-flex flex-wrap gap-2">
      @can('menus.update')<a class="btn btn-sm btn-outline-primary" href="{{ route('admin.menus.edit', $item) }}"><i class="fa-solid fa-pen me-1" aria-hidden="true"></i>Sửa</a>@endcan
      @can('menus.update')
        @if($moveItem)
          <a class="btn btn-sm btn-outline-info" href="{{ route('admin.menus.move.form', $item) }}" data-menu-move-trigger data-menu-id="{{ $item->id }}" data-menu-name="{{ $item->name_vi }}" data-menu-parent="{{ $item->parent_id ?? '' }}" data-menu-location="{{ $item->location }}" data-menu-subtree-height="{{ $moveItem['subtree_height'] }}" data-menu-move-url="{{ route('admin.menus.move', $item) }}"><i class="fa-solid fa-right-left me-1" aria-hidden="true"></i>Di chuyển</a>
        @endif
        <form method="POST" action="{{ route('admin.menus.status.update', $item) }}" data-menu-status-form>
          @csrf @method('PATCH')<input type="hidden" name="status" value="{{ $item->status ? 0 : 1 }}">
          <button class="btn btn-sm {{ $item->status ? 'btn-outline-secondary' : 'btn-outline-success' }}" type="submit" data-menu-status-button><i class="fa-solid {{ $item->status ? 'fa-eye-slash' : 'fa-eye' }} me-1" aria-hidden="true"></i><span>{{ $item->status ? 'Ẩn' : 'Hiện' }}</span></button>
        </form>
        @foreach(['up' => ['Lên', 'fa-arrow-up'], 'down' => ['Xuống', 'fa-arrow-down']] as $direction => [$label, $icon])
          <form method="POST" action="{{ route('admin.menus.reorder') }}" data-menu-reorder-form data-direction="{{ $direction }}">
            @csrf @method('PATCH')<input type="hidden" name="location" value="{{ $item->location }}"><input type="hidden" name="parent_id" value="{{ $item->parent_id }}">@foreach($orders[$direction] ?? $scopeIds as $id)<input type="hidden" name="ordered_ids[]" value="{{ $id }}">@endforeach
            <button class="btn btn-sm btn-outline-secondary" type="submit" title="Di chuyển {{ mb_strtolower($label) }}" @disabled(!isset($orders[$direction]))><i class="fa-solid {{ $icon }}" aria-hidden="true"></i><span class="visually-hidden">{{ $label }}</span></button>
          </form>
        @endforeach
      @endcan
      @can('menus.delete')
        <form method="POST" action="{{ route('admin.menus.destroy', $item) }}" data-confirm-title="Xóa menu website" data-confirm-message="Xóa vĩnh viễn menu “{{ $item->name_vi }}”? Thao tác này không thể hoàn tác." data-confirm-button="Xóa menu">
          @csrf @method('DELETE')
          <button class="btn btn-sm btn-outline-danger" type="submit" @disabled(($item->children_count ?? 0) > 0) title="{{ ($item->children_count ?? 0) > 0 ? 'Hãy xử lý các mục con trước' : 'Xóa menu' }}"><i class="fa-solid fa-trash me-1" aria-hidden="true"></i>Xóa</button>
        </form>
      @endcan
    </div>
  </td>
</tr>
@foreach($item->children as $child)
  @include('admin.menus.partials.row', [
    'item' => $child,
    'level' => $level + 1,
    'scopeIdsByParent' => $scopeIdsByParent,
    'ancestorActive' => $ancestorActive && $item->status,
    'ancestorLabels' => [...$ancestorLabels, $item->name_vi],
    'moveCatalogById' => $moveCatalogById,
  ])
@endforeach
