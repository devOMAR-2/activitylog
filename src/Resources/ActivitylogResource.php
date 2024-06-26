<?php

namespace Rmsramos\Activitylog\Resources;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Split;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Component as Livewire;
use Rmsramos\Activitylog\ActivitylogPlugin;
use Rmsramos\Activitylog\RelationManagers\ActivitylogRelationManager;
use Rmsramos\Activitylog\Resources\ActivitylogResource\Pages\ListActivitylog;
use Rmsramos\Activitylog\Resources\ActivitylogResource\Pages\ViewActivitylog;
use Spatie\Activitylog\Models\Activity;

class ActivitylogResource extends Resource
{
    public static function getModel(): string
    {
        return Activity::class;
    }

    public static function getModelLabel(): string
    {
        return ActivitylogPlugin::get()->getLabel();
    }

    public static function getPluralModelLabel(): string
    {
        return ActivitylogPlugin::get()->getPluralLabel();
    }

    public static function getNavigationIcon(): string
    {
        return ActivitylogPlugin::get()->getNavigationIcon();
    }

    public static function getNavigationLabel(): string
    {
        return Str::title(static::getPluralModelLabel()) ?? Str::title(static::getModelLabel());
    }

    public static function getNavigationSort(): ?int
    {
        return ActivitylogPlugin::get()->getNavigationSort();
    }

    public static function getNavigationGroup(): ?string
    {
        return ActivitylogPlugin::get()->getNavigationGroup();
    }

    public static function getNavigationBadge(): ?string
    {
        return ActivitylogPlugin::get()->getNavigationCountBadge() ?
            number_format(static::getModel()::count()) : null;
    }

    protected static ?string $activeNavigationIcon = 'heroicon-s-presentation-chart-line';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Split::make([
                    Section::make([
                        TextInput::make('causer_id')
                            ->afterStateHydrated(function ($component, ?Model $record) {
                                /** @phpstan-ignore-next-line */
                                return $component->state($record->causer?->name);
                            })
                            ->label(__('activitylog::forms.fields.causer.label')),

                        TextInput::make('subject_type')
                            ->afterStateHydrated(function ($component, ?Model $record, $state) {
                                /** @var Activity&ActivityModel $record */
                                return $state ? $component->state(static::translateModel(Str::of($state)->afterLast('\\')->headline()) . ' # ' . $record->subject_id) : '-';
                            })
                            ->label(__('activitylog::forms.fields.subject_type.label')),

                        Textarea::make('description')
                            ->label(__('activitylog::forms.fields.description.label'))
                            ->rows(2)
                            ->columnSpan('full'),
                    ]),
                    Section::make([
                        Placeholder::make('log_name')
                            ->content(function (?Model $record): string {
                                /** @var Activity&ActivityModel $record */
                                return $record->log_name ? ucwords($record->log_name) : '-';
                            })
                            ->label(__('activitylog::forms.fields.log_name.label')),

                            Placeholder::make('event')
                            ->content(function (?Model $record): string {
                                /** @phpstan-ignore-next-line */
                                return $record?->event ? static::translateEvent($record?->event) : '-';
                            })
                            ->label(__('activitylog::forms.fields.event.label')),

                        Placeholder::make('created_at')
                            ->label(__('activitylog::forms.fields.created_at.label'))
                            ->content(function (?Model $record): string {
                                /** @var Activity&ActivityModel $record */
                                return $record->created_at ? "{$record->created_at->format(config('activitylog.datetime_format', 'd/m/Y H:i:s'))}" : '-';
                            }),
                    ])->grow(false),
                ])->from('md'),

                Section::make()
                    ->columns()
                    ->visible(fn ($record) => $record->properties?->count() > 0)
                    ->schema(function (?Model $record) {
                        /** @var Activity&ActivityModel $record */
                        $properties = $record->properties->except(['attributes', 'old']);

                        $schema = [];

                        if ($properties->count()) {
                            $schema[] = KeyValue::make('properties')
                                ->label(__('activitylog::forms.fields.properties.label'))
                                ->afterStateHydrated(function (KeyValue $component, ?Model $record) {
                                    $component->state(static::translateProperties($record->properties->except(['attributes', 'old'])));
                                })
                                ->columnSpan('full');
                        }

                        if ($old = $record->properties->get('old')) {
                            $schema[] = KeyValue::make('old')
                                ->afterStateHydrated(function (KeyValue $component) use ($old) {
                                    $component->state(static::translateProperties($old));
                                })
                                ->label(__('activitylog::forms.fields.old.label'));
                        }

                        if ($attributes = $record->properties->get('attributes')) {
                            $schema[] = KeyValue::make('attributes')
                                ->afterStateHydrated(function (KeyValue $component) use ($attributes) {
                                    $component->state(static::translateProperties($attributes));
                                })
                                ->label(__('activitylog::forms.fields.attributes.label'));
                        }

                        return $schema;
                    }),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::getLogNameColumnCompoment(),
                static::getEventColumnCompoment(),
                static::getSubjectTypeColumnCompoment(),
                static::getCauserNameColumnCompoment(),
                static::getCreatedAtColumnCompoment(),
            ])
            ->filters([
                static::getDateFilterComponent(),
                static::getEventFilterCompoment(),
            ]);
    }

    public static function getLogNameColumnCompoment(): Column
    {
        return TextColumn::make('log_name')
            ->label(__('activitylog::tables.columns.log_name.label'))
            ->badge()
            ->formatStateUsing(fn ($state) => ucwords($state))
            ->sortable();
    }

    public static function getEventColumnCompoment(): Column
    {
        return TextColumn::make('event')
            ->label(__('activitylog::tables.columns.event.label'))
            ->formatStateUsing(fn ($state) => ucwords($state))
            ->badge()
            ->color(fn (string $state): string => match ($state) {
                'draft'   => 'gray',
                'updated' => 'warning',
                'created' => 'success',
                'deleted' => 'danger',
            })
            ->formatStateUsing(fn ($state) => static::translateEvent($state))
            ->sortable();
    }

    public static function getSubjectTypeColumnCompoment(): Column
    {
        return TextColumn::make('subject_type')
            ->label(__('activitylog::tables.columns.subject_type.label'))
            ->formatStateUsing(function ($state, Model $record) {
                /** @var Activity&ActivityModel $record */
                if (! $state) {
                    return '-';
                }

                return static::translateModel(Str::of($state)->afterLast('\\')->headline()) . ' # ' . $record->subject_id;
            })
            ->hidden(fn (Livewire $livewire) => $livewire instanceof ActivitylogRelationManager);
    }

    public static function getCauserNameColumnCompoment(): Column
    {
        return TextColumn::make('causer.name')
            ->label(__('activitylog::tables.columns.causer.label'))
            ->getStateUsing(function (Model $record) {

                if ($record->causer_id == null) {
                    return new HtmlString('&mdash;');
                }

                return $record->causer->name;
            })
            ->searchable();
    }

    public static function getPropertiesColumnCompoment(): Column
    {
        return ViewColumn::make('properties')
            ->label(__('activitylog::tables.columns.properties.label'))
            ->view('activitylog::filament.tables.columns.activity-logs-properties')
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function getCreatedAtColumnCompoment(): Column
    {
        return TextColumn::make('created_at')
            ->label(__('activitylog::tables.columns.created_at.label'))
            ->dateTime(config('activitylog.datetime_format', 'd/m/Y H:i:s'))
            ->sortable();
    }

    public static function getDateFilterComponent(): Filter
    {
        return Filter::make('created_at')
            ->label(__('activitylog::tables.filters.created_at.label'))
            ->indicateUsing(function (array $data): array {
                $indicators = [];

                if ($data['created_from'] ?? null) {
                    $indicators['created_from'] = __('activitylog::tables.filters.created_at.created_from') . Carbon::parse($data['created_from'])->toFormattedDateString();
                }

                if ($data['created_until'] ?? null) {
                    $indicators['created_until'] = __('activitylog::tables.filters.created_at.created_until') . Carbon::parse($data['created_until'])->toFormattedDateString();
                }

                return $indicators;
            })
            ->form([
                DatePicker::make('created_from')
                    ->label(__('activitylog::tables.filters.created_at.created_from')),
                DatePicker::make('created_until')
                    ->label(__('activitylog::tables.filters.created_at.created_until')),
            ])
            ->query(function (Builder $query, array $data): Builder {
                return $query
                    ->when(
                        $data['created_from'],
                        fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                    )
                    ->when(
                        $data['created_until'],
                        fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                    );
            });
    }

    public static function getEventFilterCompoment(): SelectFilter
    {
        return SelectFilter::make('event')
            ->label(__('activitylog::tables.filters.event.label'))
            ->options(self::getTranslatedEvents());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivitylog::route('/'),
            'view'  => ViewActivitylog::route('/{record}'),
        ];
    }

    public static function canAccess(): bool
    {
        $policy = Gate::getPolicyFor(static::getModel());

        if ($policy && method_exists($policy, 'viewAny')) {
            return static::canViewAny();
        } else {
            return ActivitylogPlugin::get()->isAuthorized();
        }
    }

    private static function getTranslatedEvents(): array
    {
        $events = static::getModel()::distinct()->pluck('event', 'event')->toArray();

        return array_map(function ($event) {
            return static::translateEvent($event);
        }, $events);
    }

    private static function translateEvent(string $event): string
    {
        return match ($event) {
            'draft' => 'مسودة',
            'created' => 'إنشاء',
            'updated' => 'تعديل',
            'deleted' => 'حذف',
            default => $event,
        };
    }

    private static function translateModel(string $model): string
    {
        return match ($model) {
            'Brand' => 'ماركة',
            'Car' => 'مركبة',
            'Color' => 'لون',
            'Contact' => 'رسالة',
            'Faq' => 'سؤال',
            'Feature' => 'ميزة',
            'Image' => 'صورة',
            'Policy' => 'سياسة',
            'Specification' => 'صفة',
            'Type' => 'نوع',
            'User' => 'مستخدم',
            default => $model,
        };
    }

    private static function translateProperties(array $properties): array
    {
        $translatedProperties = [];
        foreach ($properties as $key => $value) {
            $translatedProperties[static::translatePropertyKey($key)] = $value;
        }
        return $translatedProperties;
    }

    private static function translatePropertyKey(string $key): string
    {
        return match ($key) {
            'name' => 'الاسم',
            'slug' => 'عنوان الرابط',
            'country' => 'الدولة',
            'img' => 'الصورة',
            'description' => 'الوصف',
            'model' => 'الطراز',
            'brand_id' => 'الماركة',
            'type_id' => 'النوع',
            'year' => 'السنة',
            'trim' => 'الفئة',
            'value' => 'القيمة',
            'phone_number' => 'رقم الهاتف',
            'email' => 'البريد الإلكتروني',
            'subject' => 'الموضوع',
            'message' => 'الرسالة',
            'is_replyed' => 'تم الرد',
            'replyed_at' => 'تاريخ الرد',
            'question' => 'السؤال',
            'answer' => 'الإجابة',
            'car_id' => 'المركبة',
            'color_id' => 'اللون',
            'path' => 'المسار',
            'title' => 'العنوان',
            'content' => 'المحتوى',
            'version' => 'الإصدار',
            default => $key,
        };
    }
}
