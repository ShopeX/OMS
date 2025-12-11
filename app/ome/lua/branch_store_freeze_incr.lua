--[[
SKU仓库存缓存在REDIS，通过LUA脚本增加冻结实现原子性

@author: chenping@shopex.cn
@time: 2024-07-07
--]]

-- 检查库存是否充足
local function check(sku_id, quantity, store_quantity, log_type)
    local data = redis.call('HMGET', sku_id, 'store', 'store_freeze')

    -- redis未缓存到库存, 返回错误
    if not data[1] then
        return {100, 'MISSING_STOCK', sku_id}
    end

    -- redis未缓存到冻结, 返回错误
    if not data[2] then
        return {100, 'MISSING_FREEZE', sku_id}
    end

    -- 如果传入了 store_quantity，则进行库存充足性检查，
    -- php的redis回滚不会传store_quantity，需无脑回滚
    if store_quantity ~= nil then
        -- 检查冻结数量是否为有效数字，确保math.abs不会报错
        -- math.abs为取绝对值，应对store_freeze为负的情况
        local freeze_quantity = tonumber(data[2])
        if freeze_quantity == nil then
            return {100, 'INVALID_FREEZE_TYPE', sku_id}
        end
        
        -- 库存数用mysql的进行对比，并且log_type不等于negative_stock的时候
        if ((tonumber(store_quantity) - math.abs(freeze_quantity)) < math.abs(quantity)) and log_type ~= 'negative_stock' then
            return {200, 'INSUFFICIENT_STOCK', sku_id}
        end
    end

   return {0, 'success', data}
end

-- 更新冻结函数
local function update(sku_id, quantity)
    return redis.call('HINCRBY', sku_id, 'store_freeze', quantity)
end

--[[
检查库存是否充足
KEYS格式: stock:#仓库ID#:#物料ID#, 示例: stock:1:1
--]]
for i, v in ipairs(KEYS) do
    local quantity = tonumber(ARGV[i])
    local store_quantity = tonumber(ARGV[#KEYS+i])
    local log_type = ARGV[#KEYS*2+i]

    local result = check(v, quantity, store_quantity, log_type)

    if result[1] ~= 0 then
        return result
    end
end

-- 更新冻结
for i, v in ipairs(KEYS) do
    local result = update(v, tonumber(ARGV[i]))

    if not result then
        return {300, 'FAIL_FREEZE', v}
    end
end

return {0, 'success'}