function simpleQuery(queryUrl)
{
    $.ajax({
        type: 'GET',
        url: queryUrl,
        success: response => console.log(response),
        failure: error    => console.log(error)
    });
}