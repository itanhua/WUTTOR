/**
 * 滑块拼图验证码组件
 * 用法：在表单内放置 <div id="captchaMountX" class="captcha-mount"
 *                    data-captcha-mode="inline|floating"></div>，
 *       并在提交前调用  Captcha.ensure('scene', mountEl).then(function(sol){ ...提交... })
 * 若场景未开启（后端未渲染 mount 或 data-captcha-disabled），ensure 直接 resolve(null)。
 *
 * 模式（data-captcha-mode）：
 *   - inline（默认）：把滑块组件直接渲染到 mount 内部，登录/注册/发帖页等短表单用
 *   - floating：mount 仅作为触发点 + data-solved 缓存容器；用户调用 ensure() 时弹出「贴在视口
 *                当前发表位置」的弹窗，避免长帖子楼层内滑块被埋在列表底部还要往前翻。完成后
 *                自动关闭弹窗。
 *
 * 关键修正（2026-08-26）：
 *   1) 坐标换算：拖动得到的是「显示像素 curX」，但后端存储的缺口横坐标 x 是 300px 图像坐标系。
 *      必须把 curX 换算回图像坐标系 reportedX = curX * imgW / stage.clientWidth。
 *   2) 幂等渲染：同一 mount 已在渲染中用 mount._capPromise 复用。
 *   3) 加载失败优雅拒绝：captcha/create 失败时 reject，提交按钮恢复可重试。
 *   4) 弹窗贴发表位置：floating 模式 backdrop 用 flex-end + 底部留白，让 modal 出现在视口下半
 *      （紧贴用户当前的视线焦点"提交按钮所在楼层"），modal 标题按 scene 自适应「回复评论前
 *      请完成验证 / 发布前请完成验证 …」避免用户不知道是为哪个动作冒出来的。
 *   5) 弹出前 scrollIntoView：调用 Captcha.ensure 时先把 mount 滚到视口中部，保证 modal 看上去
 *      紧贴触发按钮而不会出现在 viewport 之外。
 *
 * 关键修正（2026-08-31）：
 *   6) "已通过验证"占位条增加"换一张"按钮：用户验证通过后若填错其它字段想重置，不用再刷整页
 *      重新输入。点击"换一张"会清掉 mount 的 data-solved/data-sol 并重新调 createWidget 渲染
 *      新拼图，验证后再次覆盖占位条（刷新入口常在，不是一次性的）。
 *   7) 占位条加倒计时 + 到期自动重置：后端把 expires_in（默认 60 秒）返回前端，createWidget
 *      记录 ttl/issuedAt，验证通过时算出"真实剩余秒数"交给 showSolved。占位条每秒刷新，
 *      最后 10 秒整条转橙，归零自动重画拼图并 toast 提示。
 *      - 剩余秒数必须从"拼图签发时刻"起算（不是从验证通过起算），否则与后端 expires_at
 *        对不上，会出现"前端显示还有 30s、后端已判过期"的错位。
 *      - showSolved 重入前必须 clearInterval 清掉 mount._capTimer，否则多个计时器并存，
 *        表现为倒计时乱跳、提前重置。
 *      - 业务代码都是"提交时才调 ensure() 取 sol"（未提前缓存），所以倒计时归零清掉
 *        data-solved 后，下次提交会正确要求重拖。
 */
(function () {
    // modal 自下而上"贴发表位置"涌现动画（小细节，提升存在感）
    var style = document.createElement('style');
    style.textContent = '@keyframes capSlideUp{from{opacity:0;transform:translateY(40px);}to{opacity:1;transform:translateY(0);}}';
    document.head.appendChild(style);

    var Captcha = {};

    /**
     * 统一入口：表单提交前调用本方法等一个解。
     * @param {string} scene   业务场景 key（与后端 captcha_scenes 一致：register/login/post/comment）
     * @param {HTMLElement} mount
     *        - data-captcha-disabled="1"  → 场景关闭，直接 resolve(null) 走业务提交
     *        - data-solved="1" + data-sol="..." → 之前已拖过，复用结果
     *        - data-captcha-mode="floating" → 提交时才弹窗；其它 → 内联进 mount
     * @returns Promise<{token,x}|null>
     *          null = 场景未启用 / 不需要验证；非空 = 已通过验证，前端拿 token/x 提交后端再消耗
     */
    Captcha.ensure = function (scene, mount) {
        return new Promise(function (resolve, reject) {
            if (!mount || mount.getAttribute('data-captcha-disabled') === '1') {
                return resolve(null);
            }
            if (mount.getAttribute('data-solved') === '1') {
                try { return resolve(JSON.parse(mount.getAttribute('data-sol'))); } catch (e) {}
            }
            // 幂等：同一 mount 已在渲染中则复用同一个 Promise
            if (mount._capPromise) {
                return mount._capPromise.then(resolve, reject);
            }
            // 弹出前先把 mount 滚到视口中部（不是顶部，避免顶栏遮住按钮），
            // 这样 floating 弹窗才像是「紧贴当前提交按钮冒出来」。
            try {
                if (typeof mount.scrollIntoView === 'function') {
                    mount.scrollIntoView({ block: 'center', behavior: 'smooth' });
                }
            } catch (e) {}
            var p = new Promise(function (res, rej) {
                render(scene, mount, res, rej);
            });
            mount._capPromise = p;
            p.then(
                function (s) { mount._capPromise = null; resolve(s); },
                function (e) { mount._capPromise = null; reject(e); }
            );
        });
    };

    function render(scene, mount, resolve, reject) {
        var mode = mount.getAttribute('data-captcha-mode') || 'inline';
        if (mode === 'floating') {
            renderFloating(scene, mount, resolve, reject);
        } else {
            renderInline(scene, mount, resolve, reject);
        }
    }

    /**
     * 内联模式：直接把滑块渲染到 mount 内部（登录/注册/发帖等短表单）。
     * floating 模式下的弹窗内部也复用本函数创建滑块组件。
     *
     * onSolve({token, x})  验证通过回调（同时把 {token,x} 缓存到 mount 的 data-sol）
     * onError(msg)         验证失败 / 用户主动关闭回调
     */
    function createWidget(parent, scene, mount, onSolve, onError) {
        parent.innerHTML = '';
        var wrap = document.createElement('div');
        wrap.className = 'captcha-widget';
        wrap.style.cssText = 'border:1px solid #e5e5e5;border-radius:8px;padding:10px;background:#fafafa;';
        parent.appendChild(wrap);

        var status = document.createElement('div');
        status.style.cssText = 'font-size:12px;color:#888;margin-bottom:6px;display:flex;justify-content:space-between;align-items:center;';
        status.innerHTML = '<span class="c-text">正在加载验证…</span>' +
            '<a href="javascript:;" class="c-refresh" style="color:#ea6f5a;display:none;">刷新</a>';
        wrap.appendChild(status);
        var textEl = status.querySelector('.c-text');
        var refreshEl = status.querySelector('.c-refresh');

        var stage = document.createElement('div');
        stage.style.cssText = 'position:relative;width:300px;max-width:100%;height:150px;margin:0 auto;border-radius:6px;overflow:hidden;background:#eee;user-select:none;touch-action:none;';
        wrap.appendChild(stage);

        var track = document.createElement('div');
        track.style.cssText = 'position:relative;width:300px;max-width:100%;height:36px;margin:10px auto 0;border-radius:18px;background:#f0f0f0;overflow:hidden;';
        wrap.appendChild(track);
        var trackText = document.createElement('div');
        trackText.textContent = '拖动滑块完成拼图';
        trackText.style.cssText = 'position:absolute;left:0;right:0;top:0;line-height:36px;text-align:center;font-size:13px;color:#999;';
        track.appendChild(trackText);
        var fill = document.createElement('div');
        fill.style.cssText = 'position:absolute;left:0;top:0;height:100%;width:0;background:rgba(234,111,90,.25);';
        track.appendChild(fill);
        var handle = document.createElement('div');
        handle.style.cssText = 'position:absolute;left:0;top:0;width:36px;height:36px;border-radius:50%;background:#ea6f5a;color:#fff;display:flex;align-items:center;justify-content:center;cursor:grab;font-size:16px;';
        handle.textContent = '→';
        track.appendChild(handle);

        // 坐标：curX=显示像素拖动距离；w/pw=图像坐标系宽/拼图块宽；服务端真实缺口 x 只在校验时换算过去
        var w = 300, h = 150, pw = 42, ph = 42, y = 0, token = '', maxX = w - pw;  // y=缺口纵坐标；token=服务端为本张拼图发的令牌；maxX=最远可拖距离
        var curX = 0, dragging = false, startX = 0, startLeft = 0, done = false;      // curX=当前已拖距离；done=本张已通过（再拖也无效）
        // 有效期：ttl=后端给的秒数（captcha_ttl()，默认 60）；issuedAt=本张图签发时刻。
        // 两者相减即为"还剩多少秒"，供占位条倒计时使用（2026-08-31 新增）。
        var ttl = 60, issuedAt = Date.now();

        var bgImg = document.createElement('img');
        bgImg.style.cssText = 'position:absolute;left:0;top:0;width:100%;height:100%;';
        stage.appendChild(bgImg);
        var pieceImg = document.createElement('img');
        pieceImg.style.cssText = 'position:absolute;top:0;left:0;width:' + pw + 'px;height:' + ph + 'px;';
        stage.appendChild(pieceImg);

        function setX(x) {
            curX = Math.max(0, Math.min(maxX, x));
            pieceImg.style.left = curX + 'px';
            handle.style.left = curX + 'px';
            fill.style.width = curX + 'px';
        }

        // 把「显示像素 curX」换算成「图像坐标系横坐标」再发给后端校验
        function reportedX() {
            var cw = stage.clientWidth || w;
            if (cw === w) return Math.round(curX);
            return Math.round(curX * w / cw);
        }

        function load() {
            textEl.textContent = '正在加载验证…';
            textEl.style.color = '#888';
            refreshEl.style.display = 'none';
            fetchCaptcha(scene).then(function (d) {
                token = d.token; y = d.y; w = d.w; h = d.h; pw = d.pw; ph = d.ph;
                // 记录本张图的有效期与签发时刻；后端没给就回落 60 秒
                ttl = parseInt(d.expires_in, 10) || 60;
                issuedAt = Date.now();
                maxX = w - pw;
                bgImg.src = d.bg; pieceImg.src = d.piece;
                // 拼图块与缺口按显示宽度同比缩放，保证视觉对齐
                var cw = stage.clientWidth || w;
                var scale = cw / w;
                pieceImg.style.width = (pw * scale) + 'px';
                pieceImg.style.height = (ph * scale) + 'px';
                pieceImg.style.top = (y * scale) + 'px';
                pieceImg.style.left = '0px';
                curX = 0; done = false;
                trackText.textContent = '拖动滑块完成拼图';
                trackText.style.color = '#999';
                fill.style.background = 'rgba(234,111,90,.25)';
                handle.style.background = '#ea6f5a';
                handle.textContent = '→';
                textEl.textContent = '';
                refreshEl.style.display = 'inline';
            }).catch(function (msg) {
                textEl.textContent = msg || '验证码加载失败';
                textEl.style.color = '#f5222d';
                // 关键修正：拒绝而非永久挂起，使提交按钮恢复并可重试
                onError(msg || '验证码加载失败');
            });
        }

        function onDown(e) {
            if (done) return;
            dragging = true;
            startX = (e.touches ? e.touches[0].clientX : e.clientX);
            startLeft = curX;
            handle.style.cursor = 'grabbing';
            if (e.cancelable) e.preventDefault();
        }
        function onMove(e) {
            if (!dragging) return;
            var cx = (e.touches ? e.touches[0].clientX : e.clientX);
            var dxPx = cx - startX;
            var factor = maxX / track.clientWidth;
            setX(startLeft + dxPx * factor);
            if (e.cancelable) e.preventDefault();
        }
        function onUp() {
            if (!dragging) return;
            dragging = false;
            handle.style.cursor = 'grab';
            if (curX < 5) return;
            textEl.textContent = '校验中…';
            checkCaptcha(scene, token, reportedX()).then(function () {
                done = true;
                mount.setAttribute('data-solved', '1');
                var sol = { token: token, x: reportedX() };
                mount.setAttribute('data-sol', JSON.stringify(sol));
                trackText.textContent = '验证通过';
                trackText.style.color = '#52c41a';
                fill.style.background = 'rgba(82,196,26,.25)';
                handle.style.background = '#52c41a';
                handle.textContent = '✓';
                textEl.textContent = '已通过验证';
                textEl.style.color = '#52c41a';
                // 把"剩余有效秒数"一并交给调用方：从签发到现在已经耗掉的时间要扣掉，
                // 否则用户拖了 20 秒才对上，占位条却还显示满额 60s，误导。
                var remaining = Math.max(0, ttl - Math.floor((Date.now() - issuedAt) / 1000));
                onSolve(sol, remaining);
            }).catch(function (msg) {
                textEl.textContent = msg || '验证失败，请重试';
                textEl.style.color = '#f5222d';
                setX(0);
            });
        }

        handle.addEventListener('mousedown', onDown);
        window.addEventListener('mousemove', onMove);
        window.addEventListener('mouseup', onUp);
        handle.addEventListener('touchstart', onDown, { passive: false });
        window.addEventListener('touchmove', onMove, { passive: false });
        window.addEventListener('touchend', onUp);
        refreshEl.addEventListener('click', function () { setX(0); load(); });

        load();
    }

    /**
     * 内联模式：把滑块直接渲染到 mount 内。完成后在 mount 上保留 "✓ 已通过验证" 提示。
     *
     * 验证通过后占位条上始终保留一个"换一张"按钮 —— 用户填错其它字段时不用刷整页就能
     * 重新拖一块新拼图：清掉 data-solved/data-sol 后重新调 createWidget，新的 onSolve
     * 会再次覆盖占位（保持刷新入口常在）。
     */
    function renderInline(scene, mount, resolve, reject) {
        // 每次重新进入时清掉可能遗留的"已通过"占位，让滑块能重新占位 mount
        var oldSolved = mount.querySelector('.captcha-solved-indicator');
        if (oldSolved) oldSolved.remove();
        mount.removeAttribute('data-solved');
        mount.removeAttribute('data-sol');

        createWidget(mount, scene, mount, function (sol, remaining) {
            // 占位条渲染 + 注入"换一张/到期重置"回调
            showSolved(mount, remaining, function (expired) {
                // expired=true 表示倒计时归零自动触发（非用户手动点击）：
                // 清掉 mount 缓存 + 重新调 createWidget，新的 onSolve 会再走 showSolved 覆盖占位条。
                renderInline(scene, mount, resolve, reject);
                // 自动重置要提示一句，否则用户正填着表，滑块莫名其妙自己变了会懵。
                // 手动点"换一张"不提示（用户自己点的，知道发生了什么）。
                if (expired && typeof toast === 'function') {
                    toast('验证已过期，已为你刷新验证码');
                }
            });
            // resolve 给外层 ensure；后续 ensure() 命中 data-solved 也会走同一份 sol
            resolve(sol);
        }, reject);
    }

    /**
     * 在 mount 内渲染"验证已通过"占位条。
     *
     * @param {HTMLElement} mount
     * @param {number} remaining  剩余有效秒数（由 createWidget 的 onSolve 传入）
     * @param {Function} onRefresh(boolean expired)  重置回调；expired=true 表示倒计时归零自动触发，
     *                                               false 表示用户手动点"换一张"。
     *
     * 带倒计时：每秒刷新剩余秒数，最后 10 秒转橙色警示，归零时自动调 onRefresh(true)
     * 重画拼图 —— 避免用户慢悠悠填完表，点提交才被后端告知"验证码已过期"。
     */
    function showSolved(mount, remaining, onRefresh) {
        // 关键：渲染新占位条前必须清掉上一轮的 interval。否则 renderInline 重入
        // （用户点"换一张"或到期自动重置）会留下多个计时器同时改同一段文案，
        // 表现为倒计时数字乱跳、甚至提前触发重置。
        if (mount._capTimer) {
            clearInterval(mount._capTimer);
            mount._capTimer = null;
        }

        mount.innerHTML = '';
        var ok = document.createElement('div');
        ok.className = 'captcha-solved-indicator';
        ok.style.cssText = 'border:1px solid #b7eb8f;background:#f6ffed;color:#52c41a;border-radius:8px;padding:10px 12px;font-size:13px;display:flex;align-items:center;justify-content:center;gap:10px;position:relative;';

        var label = document.createElement('span');
        label.style.cssText = 'flex:1;text-align:center;';
        // 2026-08-31：去掉「（再次提交将直接放行）」括号。原先这句话现在反而误导——
        // 有了倒计时后，用户可能误以为验证通过就一直有效，60 秒到期自动重置时一脸懵。
        label.innerHTML = '<i class="fa-solid fa-check-circle"></i> 验证已通过';
        ok.appendChild(label);

        // 倒计时：等宽数字（tabular-nums）避免每秒跳动时宽度抖动
        var cd = document.createElement('span');
        cd.className = 'c-countdown';
        cd.style.cssText = 'font-size:12px;color:#52c41a;flex-shrink:0;font-variant-numeric:tabular-nums;white-space:nowrap;';
        ok.appendChild(cd);

        // 右侧"换一张"按钮：品牌红 + rotate 图标
        var refresh = document.createElement('a');
        refresh.href = 'javascript:;';
        refresh.className = 'c-refresh-solved';
        refresh.setAttribute('aria-label', '换一张拼图');
        refresh.style.cssText = 'color:#ea6f5a;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:3px;flex-shrink:0;';
        refresh.innerHTML = '<i class="fa-solid fa-rotate-right"></i> 换一张';
        refresh.addEventListener('click', function (e) {
            e.preventDefault();
            // 防止用户连点导致 renderInline 多次重入
            refresh.style.pointerEvents = 'none';
            refresh.style.opacity = '0.5';
            if (typeof onRefresh === 'function') onRefresh(false);
        });
        ok.appendChild(refresh);

        mount.appendChild(ok);

        // ---- 倒计时主体 ----
        var left = Math.max(0, parseInt(remaining, 10) || 0);

        function stopTimer() {
            if (mount._capTimer) {
                clearInterval(mount._capTimer);
                mount._capTimer = null;
            }
        }

        function tick() {
            if (left <= 0) {
                stopTimer();
                // 到期自动重置：清掉缓存 + 重画拼图（由 renderInline 负责）
                if (typeof onRefresh === 'function') onRefresh(true);
                return;
            }
            cd.textContent = left + 's 后需重新验证';
            // 最后 10 秒整条转橙，给用户一个"该提交了"的信号
            if (left <= 10) {
                cd.style.color = '#d46b08';
                ok.style.borderColor = '#ffd591';
                ok.style.background = '#fff7e6';
                ok.style.color = '#d46b08';
            }
            left--;
        }

        tick();
        // 只有还没归零才起 interval；tick() 内已归零时直接走重置，不再挂表
        if (left >= 0 && !mount._capTimer) {
            mount._capTimer = setInterval(tick, 1000);
        }
    }

    /**
     * 悬浮弹窗模式（帖子页评论/楼中楼）：mount 平时为空，用户点击提交时弹出弹窗。
     * 弹窗布局：fixed 全屏遮罩 + modal 出现在视口下半（贴用户当前视线焦点"发表位置"），
     * 而不是居中——避免用户以为弹窗属于无关动作。
     * 弹窗关闭 / 点遮罩 / ESC 都会以 reject 终止当前 ensure 调用，提交按钮恢复可重试。
     * 弹窗内复用 createWidget() 创建滑块。
     */
    function renderFloating(scene, mount, resolve, reject) {
        var dismissed = false;
        var onKeydown = null;

        function dismiss(reason) {
            if (dismissed) return;
            dismissed = true;
            if (onKeydown) document.removeEventListener('keydown', onKeydown);
            if (backdrop && backdrop.parentNode) backdrop.parentNode.removeChild(backdrop);
            reject(new Error(reason || '已关闭'));
        }

        // 各场景标题 & icon：让用户清楚弹窗是为哪个动作冒出来的
        // 场景取值与后端 captcha_scenes 的 JSON key 一致：register / login / post / comment
        var sceneMeta = {
            comment: { icon: '📝', text: '回复评论前请完成验证' },
            post:    { icon: '📝', text: '发布帖子前请完成验证' },
            login:   { icon: '🔐', text: '登录前请完成验证' },
            register:{ icon: '📝', text: '注册前请完成验证' },
        };
        var meta = sceneMeta[scene] || { icon: '🔒', text: '请完成验证' };

        // 遮罩：modal 用 flex-end 贴视口下半 + 底部 8vh 留白，确保 modal 出现在
        // 「用户的当前视线焦点附近」（也就是他刚刚点的提交按钮所处楼层）。
        // 比单纯 fixed 居中更符合「独立悬浮到当前发表位置」的诉求。
        var backdrop = document.createElement('div');
        backdrop.className = 'captcha-modal-backdrop';
        backdrop.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9998;display:flex;align-items:flex-end;justify-content:center;padding-bottom:10vh;';

        var modal = document.createElement('div');
        modal.className = 'captcha-modal';
        modal.style.cssText = 'background:#fff;border-radius:10px;padding:18px 20px 20px;max-width:340px;width:92%;position:relative;box-shadow:0 10px 40px rgba(0,0,0,.3);margin-bottom:8px;animation:capSlideUp .25s ease-out;';
        backdrop.appendChild(modal);

        // 弹窗在头顶带一根小三角指向上方触发按钮位置（视觉锚点）
        var arrow = document.createElement('div');
        arrow.style.cssText = 'position:absolute;top:-9px;left:50%;transform:translateX(-50%);width:18px;height:18px;background:#fff;transform:translateX(-50%) rotate(45deg);border-radius:3px;box-shadow:-2px -2px 4px rgba(0,0,0,.06);';
        modal.appendChild(arrow);

        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.setAttribute('aria-label', '关闭');
        closeBtn.innerHTML = '&times;';
        closeBtn.style.cssText = 'position:absolute;top:6px;right:10px;background:none;border:none;font-size:24px;color:#999;cursor:pointer;line-height:1;padding:0;width:32px;height:32px;';
        closeBtn.addEventListener('click', function () { dismiss('已关闭'); });
        modal.appendChild(closeBtn);

        var title = document.createElement('div');
        title.innerHTML = '<span style="font-size:18px;margin-right:6px;">' + meta.icon + '</span><span style="font-size:15px;font-weight:600;color:#333;">' + meta.text + '</span>';
        title.style.cssText = 'margin-bottom:10px;padding-right:24px;display:flex;align-items:center;';
        modal.appendChild(title);

        var hint = document.createElement('div');
        hint.textContent = '为防止恶意灌水，请拖动滑块完成拼图。';
        hint.style.cssText = 'font-size:12px;color:#888;margin-bottom:10px;';
        modal.appendChild(hint);

        var widgetMount = document.createElement('div');
        modal.appendChild(widgetMount);

        document.body.appendChild(backdrop);
        // 点遮罩关闭，点 modal 内部不关闭
        backdrop.addEventListener('click', function (e) {
            if (e.target === backdrop) dismiss('已关闭');
        });
        onKeydown = function (e) { if (e.key === 'Escape') dismiss('已关闭'); };
        document.addEventListener('keydown', onKeydown);

        createWidget(widgetMount, scene, mount, function (sol) {
            // 验证通过：关闭弹窗 → resolve
            if (dismissed) return;
            dismissed = true;
            if (onKeydown) document.removeEventListener('keydown', onKeydown);
            if (backdrop && backdrop.parentNode) backdrop.parentNode.removeChild(backdrop);
            resolve(sol);
        }, function (msg) {
            // 加载失败/校验失败：让 createWidget 自己重试（在弹窗内），不关弹窗
            if (dismissed) return;
            // 网络/服务错误透传给外层 reject，让提交按钮恢复可重试
            dismiss(msg);
        });
    }

    // 向后端申请一张拼图（背景图 + 拼图块 PNG；缺口横坐标 x 只留在服务端，前端只能肉眼对齐）
    function fetchCaptcha(scene) {
        return new Promise(function (resolve, reject) {
            fetch(url('captcha/create', { scene: scene }), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); }).then(function (res) {
                if (res.code === 0 && res.data) resolve(res.data);
                else reject(res.message || '验证码加载失败');
            }).catch(function () { reject('网络错误'); });
        });
    }

    // 拖动结束后向 captcha/check 发出对齐校验（仅判断，不消耗 token）。
    // 真正的"消耗式校验"在业务 action 的 captcha_guard 里做，前端不能信。
    function checkCaptcha(scene, token, x) {
        return new Promise(function (resolve, reject) {
            var body = 'scene=' + encodeURIComponent(scene) +
                '&token=' + encodeURIComponent(token) +
                '&x=' + Math.round(x) +
                '&_token=' + encodeURIComponent(getCsrfToken());
            fetch(url('captcha/check'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: body
            }).then(function (r) { return r.json(); }).then(function (res) {
                if (res.code === 0) resolve(true);
                else reject(res.message);
            }).catch(function () { reject('网络错误'); });
        });
    }

    window.Captcha = Captcha;
})();
