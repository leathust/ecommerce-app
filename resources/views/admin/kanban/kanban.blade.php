@extends('admin.layout')
@section('content')
              <div class="page-header">
                            <div>
                                <h1>Kanban board</h1>
                                <p class="subtitle">Drag cards between columns. Click "+ Add card" to draft a new task.</p>
                            </div>
                            <div class="page-header__actions">
                                <button type="button" class="m-btn m-btn--ghost"><i class="fa-solid fa-filter"></i> Filter</button>
                                <button type="button" class="m-btn m-btn--primary" id="kanban-new-btn"><i class="fa-solid fa-plus"></i> New card</button>
                            </div>
                        </div>

                        <div class="kanban-board" id="kanban-board">
                            <!-- BACKLOG -->
                            <section class="kanban-col" data-status="backlog">
                                <header class="kanban-col__header">
                                    <h2 class="kanban-col__title">
                                        <span class="kanban-col__rail kanban-col__rail--backlog"></span> Backlog
                                        <span class="kanban-col__count">5</span>
                                    </h2>
                                    <button type="button" class="icon-btn" aria-label="Column options"><i class="fa-solid fa-ellipsis"></i></button>
                                </header>
                                <div class="kanban-col__list">
                                    <article class="kanban-card" draggable="true">
                                        <div class="kanban-card__labels"><span class="kanban-card__label kanban-card__label--feature">Feature</span></div>
                                        <p class="kanban-card__title">Add CSV export to data tables</p>
                                        <div class="kanban-card__footer">
                                            <span class="kanban-card__meta"><i class="fa-regular fa-comment"></i>3 <i class="fa-regular fa-paperclip"></i>1</span>
                                            <span class="kanban-card__avatars"><img src="images/icon/avatar-04.jpg" alt=""></span>
                                        </div>
                                    </article>
                                    <article class="kanban-card" draggable="true">
                                        <div class="kanban-card__labels"><span class="kanban-card__label kanban-card__label--research">Research</span></div>
                                        <p class="kanban-card__title">User testing — onboarding flow v2</p>
                                        <div class="kanban-card__footer">
                                            <span class="kanban-card__meta"><i class="fa-regular fa-calendar"></i>May 22</span>
                                            <span class="kanban-card__avatars"><img src="images/icon/avatar-05.jpg" alt=""><img src="images/icon/avatar-06.jpg" alt=""></span>
                                        </div>
                                    </article>
                                    <article class="kanban-card" draggable="true">
                                        <div class="kanban-card__labels"><span class="kanban-card__label kanban-card__label--bug">Bug</span></div>
                                        <p class="kanban-card__title">Sidebar collapses unexpectedly on Safari iOS</p>
                                        <div class="kanban-card__footer">
                                            <span class="kanban-card__meta"><i class="fa-regular fa-comment"></i>7</span>
                                            <span class="kanban-card__avatars"><img src="images/icon/avatar-01.jpg" alt=""></span>
                                        </div>
                                    </article>
                                    <article class="kanban-card" draggable="true">
                                        <div class="kanban-card__labels"><span class="kanban-card__label kanban-card__label--docs">Docs</span></div>
                                        <p class="kanban-card__title">Document the chart factory helpers</p>
                                        <div class="kanban-card__footer">
                                            <span class="kanban-card__meta"><i class="fa-regular fa-paperclip"></i>2</span>
                                            <span class="kanban-card__avatars"><img src="images/icon/avatar-03.jpg" alt=""></span>
                                        </div>
                                    </article>
                                    <article class="kanban-card" draggable="true">
                                        <div class="kanban-card__labels"><span class="kanban-card__label kanban-card__label--feature">Feature</span></div>
                                        <p class="kanban-card__title">Bulk-edit selected rows in tables</p>
                                        <div class="kanban-card__footer">
                                            <span class="kanban-card__meta"><i class="fa-regular fa-calendar"></i>Jun 02</span>
                                            <span class="kanban-card__avatars"><img src="images/icon/avatar-02.jpg" alt=""></span>
                                        </div>
                                    </article>
                                </div>
                                <div class="kanban-col__add"><button type="button"><i class="fa-solid fa-plus"></i> Add card</button></div>
                            </section>

                            <!-- IN PROGRESS -->
                            <section class="kanban-col" data-status="progress">
                                <header class="kanban-col__header">
                                    <h2 class="kanban-col__title">
                                        <span class="kanban-col__rail kanban-col__rail--progress"></span> In progress
                                        <span class="kanban-col__count">4</span>
                                    </h2>
                                    <button type="button" class="icon-btn" aria-label="Column options"><i class="fa-solid fa-ellipsis"></i></button>
                                </header>
                                <div class="kanban-col__list">
                                    <article class="kanban-card" draggable="true">
                                        <div class="kanban-card__labels"><span class="kanban-card__label kanban-card__label--feature">Feature</span><span class="kanban-card__label kanban-card__label--design">Design</span></div>
                                        <p class="kanban-card__title">Q2 dashboard redesign — sidebar + topbar</p>
                                        <div class="kanban-card__footer">
                                            <span class="kanban-card__meta"><i class="fa-regular fa-calendar"></i>May 18 <i class="fa-regular fa-comment"></i>12</span>
                                            <span class="kanban-card__avatars"><img src="images/icon/avatar-01.jpg" alt=""><img src="images/icon/avatar-04.jpg" alt=""><img src="images/icon/avatar-06.jpg" alt=""></span>
                                        </div>
                                    </article>
                                    <article class="kanban-card" draggable="true">
                                        <div class="kanban-card__labels"><span class="kanban-card__label kanban-card__label--bug">Bug</span></div>
                                        <p class="kanban-card__title">Fix avatar alignment on mobile header</p>
                                        <div class="kanban-card__footer">
                                            <span class="kanban-card__meta"><i class="fa-regular fa-comment"></i>4</span>
                                            <span class="kanban-card__avatars"><img src="images/icon/avatar-04.jpg" alt=""></span>
                                        </div>
                                    </article>
                                    <article class="kanban-card" draggable="true">
                                        <div class="kanban-card__labels"><span class="kanban-card__label kanban-card__label--feature">Feature</span></div>
                                        <p class="kanban-card__title">Cmd+K command palette</p>
                                        <div class="kanban-card__footer">
                                            <span class="kanban-card__meta"><i class="fa-regular fa-calendar"></i>Today</span>
                                            <span class="kanban-card__avatars"><img src="images/icon/avatar-03.jpg" alt=""></span>
                                        </div>
                                    </article>
                                    <article class="kanban-card" draggable="true">
                                        <div class="kanban-card__labels"><span class="kanban-card__label kanban-card__label--research">Research</span></div>
                                        <p class="kanban-card__title">Pricing page A/B test analysis</p>
                                        <div class="kanban-card__footer">
                                            <span class="kanban-card__meta"><i class="fa-regular fa-paperclip"></i>5 <i class="fa-regular fa-comment"></i>2</span>
                                            <span class="kanban-card__avatars"><img src="images/icon/avatar-05.jpg" alt=""></span>
                                        </div>
                                    </article>
                                </div>
                                <div class="kanban-col__add"><button type="button"><i class="fa-solid fa-plus"></i> Add card</button></div>
                            </section>

                            <!-- IN REVIEW -->
                            <section class="kanban-col" data-status="review">
                                <header class="kanban-col__header">
                                    <h2 class="kanban-col__title">
                                        <span class="kanban-col__rail kanban-col__rail--review"></span> In review
                                        <span class="kanban-col__count">3</span>
                                    </h2>
                                    <button type="button" class="icon-btn" aria-label="Column options"><i class="fa-solid fa-ellipsis"></i></button>
                                </header>
                                <div class="kanban-col__list">
                                    <article class="kanban-card" draggable="true">
                                        <div class="kanban-card__labels"><span class="kanban-card__label kanban-card__label--feature">Feature</span></div>
                                        <p class="kanban-card__title">Auth flow refactor — PR #2148</p>
                                        <div class="kanban-card__footer">
                                            <span class="kanban-card__meta"><i class="fa-regular fa-comment"></i>14</span>
                                            <span class="kanban-card__avatars"><img src="images/icon/avatar-04.jpg" alt=""><img src="images/icon/avatar-01.jpg" alt=""></span>
                                        </div>
                                    </article>
                                    <article class="kanban-card" draggable="true">
                                        <div class="kanban-card__labels"><span class="kanban-card__label kanban-card__label--design">Design</span></div>
                                        <p class="kanban-card__title">Pricing page — final visual review</p>
                                        <div class="kanban-card__footer">
                                            <span class="kanban-card__meta"><i class="fa-regular fa-calendar"></i>Tomorrow</span>
                                            <span class="kanban-card__avatars"><img src="images/icon/avatar-06.jpg" alt=""></span>
                                        </div>
                                    </article>
                                    <article class="kanban-card" draggable="true">
                                        <div class="kanban-card__labels"><span class="kanban-card__label kanban-card__label--docs">Docs</span></div>
                                        <p class="kanban-card__title">API v2 docs — review by reviewers</p>
                                        <div class="kanban-card__footer">
                                            <span class="kanban-card__meta"><i class="fa-regular fa-comment"></i>3</span>
                                            <span class="kanban-card__avatars"><img src="images/icon/avatar-03.jpg" alt=""></span>
                                        </div>
                                    </article>
                                </div>
                                <div class="kanban-col__add"><button type="button"><i class="fa-solid fa-plus"></i> Add card</button></div>
                            </section>

                            <!-- DONE -->
                            <section class="kanban-col" data-status="done">
                                <header class="kanban-col__header">
                                    <h2 class="kanban-col__title">
                                        <span class="kanban-col__rail kanban-col__rail--done"></span> Done
                                        <span class="kanban-col__count">6</span>
                                    </h2>
                                    <button type="button" class="icon-btn" aria-label="Column options"><i class="fa-solid fa-ellipsis"></i></button>
                                </header>
                                <div class="kanban-col__list">
                                    <article class="kanban-card" draggable="true">
                                        <div class="kanban-card__labels"><span class="kanban-card__label kanban-card__label--feature">Feature</span></div>
                                        <p class="kanban-card__title">Toast notification system</p>
                                        <div class="kanban-card__footer">
                                            <span class="kanban-card__meta"><i class="fa-regular fa-calendar"></i>May 12</span>
                                            <span class="kanban-card__avatars"><img src="images/icon/avatar-02.jpg" alt=""></span>
                                        </div>
                                    </article>
                                    <article class="kanban-card" draggable="true">
                                        <div class="kanban-card__labels"><span class="kanban-card__label kanban-card__label--feature">Feature</span></div>
                                        <p class="kanban-card__title">Calendar — date-relative event generator</p>
                                        <div class="kanban-card__footer">
                                            <span class="kanban-card__meta"><i class="fa-regular fa-comment"></i>5</span>
                                            <span class="kanban-card__avatars"><img src="images/icon/avatar-01.jpg" alt=""></span>
                                        </div>
                                    </article>
                                    <article class="kanban-card" draggable="true">
                                        <div class="kanban-card__labels"><span class="kanban-card__label kanban-card__label--bug">Bug</span></div>
                                        <p class="kanban-card__title">Search field overflow on iPhone SE</p>
                                        <div class="kanban-card__footer">
                                            <span class="kanban-card__meta"><i class="fa-regular fa-comment"></i>2</span>
                                            <span class="kanban-card__avatars"><img src="images/icon/avatar-04.jpg" alt=""></span>
                                        </div>
                                    </article>
                                    <article class="kanban-card" draggable="true">
                                        <div class="kanban-card__labels"><span class="kanban-card__label kanban-card__label--design">Design</span></div>
                                        <p class="kanban-card__title">Inbox redesign — folder tabs + email rows</p>
                                        <div class="kanban-card__footer">
                                            <span class="kanban-card__meta"><i class="fa-regular fa-paperclip"></i>3</span>
                                            <span class="kanban-card__avatars"><img src="images/icon/avatar-06.jpg" alt=""></span>
                                        </div>
                                    </article>
                                    <article class="kanban-card" draggable="true">
                                        <div class="kanban-card__labels"><span class="kanban-card__label kanban-card__label--docs">Docs</span></div>
                                        <p class="kanban-card__title">v3 release notes</p>
                                        <div class="kanban-card__footer">
                                            <span class="kanban-card__meta"><i class="fa-regular fa-calendar"></i>May 8</span>
                                            <span class="kanban-card__avatars"><img src="images/icon/avatar-03.jpg" alt=""></span>
                                        </div>
                                    </article>
                                    <article class="kanban-card" draggable="true">
                                        <div class="kanban-card__labels"><span class="kanban-card__label kanban-card__label--research">Research</span></div>
                                        <p class="kanban-card__title">Q1 customer interview synthesis</p>
                                        <div class="kanban-card__footer">
                                            <span class="kanban-card__meta"><i class="fa-regular fa-comment"></i>9</span>
                                            <span class="kanban-card__avatars"><img src="images/icon/avatar-05.jpg" alt=""></span>
                                        </div>
                                    </article>
                                </div>
                            </section>
                        </div>
                        @endsection
                        @section('script')
                        <script>
    (function () {
        const board = document.getElementById('kanban-board');
        if (!board) return;
        let draggedCard = null;

        function refreshCount(col) {
            const count = col.querySelectorAll('.kanban-card').length;
            const badge = col.querySelector('.kanban-col__count');
            if (badge) badge.textContent = count;
        }

        board.querySelectorAll('.kanban-card').forEach(setupCard);
        board.querySelectorAll('.kanban-col__list').forEach(setupColumn);
        board.querySelectorAll('.kanban-col__add button').forEach((btn) => {
            btn.addEventListener('click', () => {
                const col = btn.closest('.kanban-col');
                const list = col.querySelector('.kanban-col__list');
                const title = prompt('Card title:');
                if (!title) return;
                const card = document.createElement('article');
                card.className = 'kanban-card';
                card.draggable = true;
                card.innerHTML = '<div class="kanban-card__labels"><span class="kanban-card__label kanban-card__label--feature">Feature</span></div><p class="kanban-card__title"></p><div class="kanban-card__footer"><span class="kanban-card__meta"><i class="fa-regular fa-calendar"></i>Today</span></div>';
                card.querySelector('.kanban-card__title').textContent = title;
                list.appendChild(card);
                setupCard(card);
                refreshCount(col);
                if (window.toast) window.toast.success('Card added');
            });
        });

        const newBtn = document.getElementById('kanban-new-btn');
        if (newBtn) {
            newBtn.addEventListener('click', () => {
                const firstCol = board.querySelector('.kanban-col[data-status="backlog"] .kanban-col__add button');
                if (firstCol) firstCol.click();
            });
        }

        function setupCard(card) {
            card.addEventListener('dragstart', () => {
                draggedCard = card;
                card.classList.add('is-dragging');
            });
            card.addEventListener('dragend', () => {
                card.classList.remove('is-dragging');
                draggedCard = null;
                board.querySelectorAll('.kanban-col__list').forEach((l) => l.classList.remove('is-drop-target'));
            });
        }

        function setupColumn(list) {
            list.addEventListener('dragover', (e) => {
                e.preventDefault();
                list.classList.add('is-drop-target');
                const after = nextCardAfter(list, e.clientY);
                if (!draggedCard) return;
                if (after == null) list.appendChild(draggedCard);
                else list.insertBefore(draggedCard, after);
            });
            list.addEventListener('dragleave', (e) => {
                if (!list.contains(e.relatedTarget)) list.classList.remove('is-drop-target');
            });
            list.addEventListener('drop', () => {
                list.classList.remove('is-drop-target');
                board.querySelectorAll('.kanban-col').forEach(refreshCount);
                if (window.toast) window.toast.info('Card moved');
            });
        }

        function nextCardAfter(list, y) {
            const cards = [...list.querySelectorAll('.kanban-card:not(.is-dragging)')];
            return cards.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                if (offset < 0 && offset > closest.offset) return { offset, element: child };
                return closest;
            }, { offset: -Infinity, element: null }).element;
        }
    })();
    </script>
    @endsection