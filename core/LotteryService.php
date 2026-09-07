<?php
/**
 * 抽奖主题积分分配算法（2026-09-01）。
 *
 * 关键设计：积分奖品选「随机」模式时，**质押总额 = 用户填的总数**（不平均分），
 * 开奖时把所有积分随机分给 actualWinners 个人，每人数量不同但 ≥1，加起来恰好 = 总数。
 *
 * 校验：要求总数 ≥ 中奖人数（即「人均 ≥ 1」），否则发布时拒绝。
 * - 总数 = 中奖人数：每人恰好 1（算法自动走常量返回）
 * - 总数 > 中奖人数：人均 > 1，用「切点法」随机分段
 *
 * 算法来源（直觉）：在 [0, total - people] 区间随机生成 people-1 个切点排序，
 * 相邻切点差 + 1 即为每人份，加起来正好等于 total。
 *
 * 注意：本类仅返回「随机份额」，调用方负责实际 grant；返回的份额合计 = 入参 total。
 */
class LotteryService
{
    /**
     * 按 total 把积分随机分给 people 个人，每人 ≥ 1，加起来 = total。
     *
     * @param int $total   质押总额（必须 >= people，否则返回每人 1 的退化结果 + 全部差额返还）
     * @param int $people  实际参与分配的中奖人数
     * @return array{shares: int[], refund: int}
     *   - shares: 长度 = people，每个 ≥ 0，加起来 ≤ total
     *   - refund: total 中「未被任何人拿走」的部分，调用方负责 50% 返还作者（与既有逻辑一致）
     */
    public static function splitStakeRandomly(int $total, int $people): array
    {
        if ($people <= 0) return ['shares' => [], 'refund' => $total];
        // 边界：total == people → 每人 1，加起来 = total
        if ($total === $people) return ['shares' => array_fill(0, $people, 1), 'refund' => 0];
        // 边界：total < people → 平均不足 1，每人 1 都不够；全部剩余视为 refund
        if ($total < $people)  return ['shares' => array_fill(0, $people, 1), 'refund' => $total - $people];
        // 常规：每人先拿 1，剩余 (total - people) 用切点法随机分
        $base = 1;
        $extra = $total - $people;          // 待随机分配的剩余积分
        if ($extra === 0) return ['shares' => array_fill(0, $people, 1), 'refund' => 0];

        // 生成 people - 1 个 [0, extra] 内的随机切点并升序排序
        $cuts = [];
        for ($i = 0; $i < $people - 1; $i++) {
            $cuts[] = random_int(0, $extra);
        }
        sort($cuts, SORT_NUMERIC);

        // 相邻切点之差 + 每人基础 1，得到 people 个非空份额
        $shares = [];
        $prev = 0;
        foreach ($cuts as $c) {
            $shares[] = $base + ($c - $prev);
            $prev = $c;
        }
        $shares[] = $base + ($extra - $prev);

        // 切点法天然按序，但每段的"序号"权重可能让最后一段偏大；打乱顺序更随机
        shuffle($shares);
        return ['shares' => $shares, 'refund' => 0];
    }

    /**
     * 「平均 ≤ 1」时的恒等分布：当 total == people，每人恰好 1。
     * 当 total < people，返回的份额全为 1，refund = people - total（原本不该发生，已在前端拒绝）。
     */
    public static function splitEvenly(int $total, int $people): array
    {
        $each = $people > 0 ? intdiv($total, $people) : 0;
        $remainder = $people > 0 ? ($total - $each * $people) : $total;
        $shares = array_fill(0, $people, $each);
        // 把余数按 1 累加到前几位，让总额对齐
        for ($i = 0; $i < $remainder; $i++) $shares[$i]++;
        return ['shares' => $shares, 'refund' => 0];
    }
}