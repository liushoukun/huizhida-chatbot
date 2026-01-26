<div class="fi-ta">
    <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-start dark:divide-white/5">
        <thead class="bg-gray-50 dark:bg-white/5">
            <tr>
                <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6">
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">应用ID</span>
                </th>
                <th class="fi-ta-header-cell px-3 py-3.5">
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">应用名称</span>
                </th>
                <th class="fi-ta-header-cell px-3 py-3.5 sm:last-of-type:pe-6">
                    <span class="text-sm font-semibold text-gray-950 dark:text-white text-right">操作</span>
                </th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-white/5">
            @forelse ($applications as $app)
                <tr class="fi-ta-row transition duration-75 hover:bg-gray-50 dark:hover:bg-white/5">
                    <td class="fi-ta-cell p-0 first-of-type:ps-1">
                        <div class="fi-ta-col-wrp px-3 py-4">
                            <code class="text-sm text-gray-500 dark:text-gray-400">{{ $app['id'] }}</code>
                        </div>
                    </td>
                    <td class="fi-ta-cell p-0">
                        <div class="fi-ta-col-wrp px-3 py-4">
                            <span class="text-sm text-gray-950 dark:text-white">{{ $app['name'] }}</span>
                        </div>
                    </td>
                    <td class="fi-ta-cell p-0 last-of-type:pe-1 text-right">
                        <div class="fi-ta-col-wrp px-3 py-4 flex justify-end">
                            {{-- 这里触发嵌套 Action 的逻辑需要配合 Filament 的交互，
                                 由于是自定义 HTML 表格，我们通过点击一个隐藏的按钮或触发原生事件来实现 --}}
                            <x-filament::button
                                color="info"
                                icon="heroicon-o-users"
                                size="sm"
                                wire:click="mountAction('view_receptionists', { applicationId: '{{ $app['id'] }}', applicationName: '{{ $app['name'] }}' })"
                            >
                                查看接待人员
                            </x-filament::button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                        暂无数据
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
