<div class="fi-ta">
    <div class="mb-4 flex justify-end">
        <x-filament::button
            color="success"
            icon="heroicon-o-plus"
            size="sm"
            wire:click="mountAction('add_receptionist', { 
                applicationId: '{{ $applicationId }}', 
                applicationName: '{{ $applicationName }}'
            })"
        >
            添加接待人员
        </x-filament::button>
    </div>
    <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-start dark:divide-white/5">
        <thead class="bg-gray-50 dark:bg-white/5">
            <tr>
                <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6">
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">类型</span>
                </th>
                <th class="fi-ta-header-cell px-3 py-3.5">
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">ID</span>
                </th>
                <th class="fi-ta-header-cell px-3 py-3.5">
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">状态</span>
                </th>
                <th class="fi-ta-header-cell px-3 py-3.5 sm:last-of-type:pe-6">
                    <span class="text-sm font-semibold text-gray-950 dark:text-white text-right">操作</span>
                </th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-white/5">
            @forelse ($receptionists as $receptionist)
                <tr class="fi-ta-row transition duration-75 hover:bg-gray-50 dark:hover:bg-white/5">
                    <td class="fi-ta-cell p-0 first-of-type:ps-1">
                        <div class="fi-ta-col-wrp px-3 py-4">
                            @php
                                $typeLabel = match($receptionist['type']) {
                                    'member' => '成员',
                                    'department' => '部门',
                                    default => $receptionist['type'],
                                };
                                $typeColor = match($receptionist['type']) {
                                    'member' => 'success',
                                    'department' => 'info',
                                    default => 'gray',
                                };
                            @endphp
                            <span class="fi-badge fi-color-{{ $typeColor }} inline-flex items-center gap-x-1 rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset">
                                {{ $typeLabel }}
                            </span>
                        </div>
                    </td>
                    <td class="fi-ta-cell p-0">
                        <div class="fi-ta-col-wrp px-3 py-4">
                            <code class="text-sm text-gray-500 dark:text-gray-400">{{ $receptionist['id'] }}</code>
                        </div>
                    </td>
                    <td class="fi-ta-cell p-0">
                        <div class="fi-ta-col-wrp px-3 py-4">
                            @php
                                $statusLabel = match($receptionist['status']) {
                                    'online' => '在线',
                                    'offline' => '离线',
                                    default => $receptionist['status'],
                                };
                                $statusColor = match($receptionist['status']) {
                                    'online' => 'success',
                                    'offline' => 'gray',
                                    default => 'gray',
                                };
                            @endphp
                            <span class="fi-badge fi-color-{{ $statusColor }} inline-flex items-center gap-x-1 rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset">
                                {{ $statusLabel }}
                            </span>
                        </div>
                    </td>
                    <td class="fi-ta-cell p-0 last-of-type:pe-1 text-right">
                        <div class="fi-ta-col-wrp px-3 py-4 flex justify-end">
                            <x-filament::button
                                color="danger"
                                icon="heroicon-o-trash"
                                size="sm"
                                wire:click="mountAction('remove_receptionist', { 
                                    applicationId: '{{ $applicationId }}', 
                                    receptionistType: '{{ $receptionist['type'] }}',
                                    receptionistId: '{{ $receptionist['id'] }}',
                                    receptionistStatus: '{{ $receptionist['status'] }}',
                                    applicationName: '{{ $applicationName }}'
                                })"
                            >
                                删除
                            </x-filament::button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                        暂无数据
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
