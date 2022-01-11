--TEST--
priority_sampling default sampling
--ENV--
DD_TRACE_GENERATE_ROOT_SPAN=1
--FILE--
<?php
if (\DDTrace\get_priority_sampling() == \DD_TRACE_PRIORITY_SAMPLING_AUTO_KEEP) {
    echo "\DDTrace\get_priority_sampling() OK\n";

    $root = \DDTrace\root_span();

    if ($root->metrics["_sampling_priority_v1"] == \DD_TRACE_PRIORITY_SAMPLING_AUTO_KEEP) {
        echo "metrics[_sampling_priority_v1] OK\n";

        if ($root->metrics["_dd.rule_psr"] === 1.0) {
            echo "metrics[_dd.rule_psr] OK\n";

            if (\DDTrace\get_priority_sampling() == \DD_TRACE_PRIORITY_SAMPLING_AUTO_KEEP) {
                echo "\DDTrace\get_priority_sampling() OK\n";
            } else {
                echo "Default priority sampling changed\n";
            }
        } else {
            echo "_dd.rule_psr is missing from root span metrics\n";
        }
    } else {
        echo "_sampling_priority_v1 metric is missing from root span metrics\n";
    }
} else {
    echo "Default priority sampling is not automatically kept\n";
}
echo "_dd.p.upstream_services = {$root->meta["_dd.p.upstream_services"]}\n";
?>
--EXPECT--
\DDTrace\get_priority_sampling() OK
metrics[_sampling_priority_v1] OK
metrics[_dd.rule_psr] OK
\DDTrace\get_priority_sampling() OK
_dd.p.upstream_services = MDAxLWRlZmF1bHQtc2FtcGxpbmcucGhw|1|1|1.000
